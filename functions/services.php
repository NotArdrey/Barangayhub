<?php
session_start();
require "../config/dbconn.php";
require_once __DIR__ . '/../vendor/autoload.php'; // This file initializes the $pdo PDO connection


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/index.php");
    exit;
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
$maxFileSize = 2 * 1024 * 1024; // 2MB

if ($_FILES['uploadId']['error'] !== UPLOAD_ERR_OK) {
    throw new Exception("ID upload failed: " . $_FILES['uploadId']['error']);
}

$fileExtension = strtolower(pathinfo($_FILES['uploadId']['name'], PATHINFO_EXTENSION));
if (!in_array($fileExtension, $allowedExtensions)) {
    throw new Exception("Invalid file format. Only JPG, PNG, PDF allowed.");
}

if ($_FILES['uploadId']['size'] > $maxFileSize) {
    throw new Exception("File too large. Max 2MB allowed.");
}

// Generate unique filename
$idImageName = "id_{$userId}_" . time() . ".{$fileExtension}";
$idImagePath = __DIR__ . "/../uploads/{$idImageName}";

// Move the file
if (!move_uploaded_file($_FILES['uploadId']['tmp_name'], $idImagePath)) {
    throw new Exception("Failed to save ID file");
}

// Store relative path in DB
$idImagePath = "uploads/{$idImageName}";


if (!isset($_FILES['uploadId']) || $_FILES['uploadId']['error'] !== UPLOAD_ERR_OK) {
    throw new Exception("ID file upload failed");
}

$uploadDir = __DIR__ . '/../uploads/'; // Create this directory
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$fileExtension = pathinfo($_FILES['uploadId']['name'], PATHINFO_EXTENSION);
$idImageName = "user_{$userId}_id." . $fileExtension;
$idImagePath = $uploadDir . $idImageName;

// Move uploaded file
if (!move_uploaded_file($_FILES['uploadId']['tmp_name'], $idImagePath)) {
    throw new Exception("Failed to save ID file");
}

// You may have user email stored in the session or you might need to query it from the database.
// For this example, we'll assume it is stored in the session.
$userEmail = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';
$userName  = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';

try {
    // Begin transaction to ensure atomicity
    $pdo->beginTransaction();
    $userId = $_SESSION['user_id'];

    // Assuming $idImagePath is defined somewhere before updating the user's record.
    // For example, it could be a file upload result from another part of your code.
    // $idImagePath = ... (your code to determine the path)

    // Update the user's record with the new ID image path
    $updateUserSql = "UPDATE Users SET id_image_path = ? WHERE user_id = ?";
    $stmt = $pdo->prepare($updateUserSql);
    $stmt->execute([$idImagePath, $userId]);

    /***************** Insert Document Request Details *****************/
    // Retrieve and validate document type from POST data
    $documentType = trim($_POST['documentType']);
    $documentTypeMapping = array(
        "barangayClearance"     => 1,
        "firstTimeJobSeeker"    => 2,
        "proofOfResidency"      => 3,
        "barangayIndigency"     => 4,
        "goodMoralCertificate"  => 5,
        "noIncomeCertification" => 6
    );
    if (!isset($documentTypeMapping[$documentType])) {
        throw new Exception("Invalid document type selected.");
    }
    $document_type_id = $documentTypeMapping[$documentType];

    $request_date = date("Y-m-d H:i:s");
    $status = "Pending";
    $remarks = "";

    // Optional document-specific fields
    $clearance_purpose  = isset($_POST['purposeClearance']) ? trim($_POST['purposeClearance']) : null;
    $residency_duration = isset($_POST['residencyDuration']) ? trim($_POST['residencyDuration']) : null;
    $residency_purpose  = isset($_POST['residencyPurpose']) ? trim($_POST['residencyPurpose']) : null;
    $gmc_purpose        = isset($_POST['gmcPurpose']) ? trim($_POST['gmcPurpose']) : null;
    $nic_reason         = isset($_POST['nicReason']) ? trim($_POST['nicReason']) : null;
    $indigency_income   = (isset($_POST['indigencyIncome']) && $_POST['indigencyIncome'] !== '') ? trim($_POST['indigencyIncome']) : null;
    $indigency_reason   = isset($_POST['indigencyReason']) ? trim($_POST['indigencyReason']) : null;
    $deliveryMethod     = trim($_POST['deliveryMethod']);

            $docReqSql = "INSERT INTO DocumentRequest 
            (user_id, document_type_id, request_date, status, remarks, delivery_method)
            VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($docReqSql);
        $stmt->execute([
            $userId,
            $document_type_id,
            $request_date,
            $status,
            $remarks,
            $deliveryMethod
        ]);
        $docRequestId = $pdo->lastInsertId();

        // Insert document-specific attributes into DocRequestAttribute
        $attributes = [
            'clearance_purpose'  => $clearance_purpose,
            'residency_duration' => $residency_duration,
            'residency_purpose'  => $residency_purpose,
            'gmc_purpose'        => $gmc_purpose,
            'nic_reason'         => $nic_reason,
            'indigency_income'   => $indigency_income,
            'indigency_reason'   => $indigency_reason
        ];

        foreach ($attributes as $key => $value) {
            if ($value !== null) {
                $attrSql = "INSERT INTO DocumentRequestAttribute (request_id, attr_key, attr_value) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($attrSql);
                $stmt->execute([$docRequestId, $key, $value]);
            }
        }
    $docRequestId = $pdo->lastInsertId();

    // Log the document request insertion in the AuditTrail
    $auditSql = "INSERT INTO AuditTrail (admin_user_id, action, table_name, record_id, description) VALUES (?, 'INSERT', 'DocumentRequest', ?, ?)";
    $stmt = $pdo->prepare($auditSql);
    $stmt->execute([$userId, $docRequestId, "New document request submitted along with an updated ID image."]);

    // Commit the transaction after all queries are successful
    $pdo->commit();

    // Send confirmation email using PHPMailer
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com';             // Replace with your SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'barangayhub2@gmail.com';         // Replace with your SMTP username
        $mail->Password   = 'eisy hpjz rdnt bwrp';                  // Replace with your SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;    // Enable TLS encryption; you can also use ENCRYPTION_SMTPS
        $mail->Port       = 587;                              // TCP port to connect to

        // Recipients
        $mail->setFrom('your_email@example.com', 'Your App Name');
        $mail->addAddress($userEmail, $userName); // Add recipient. Adjust how you obtain the user's email.

        // Content
        $mail->isHTML(false);  // Set email format to plain text if desired, or use isHTML(true) for HTML content
        $mail->Subject = 'Document Request Confirmation';
        $mail->Body    = "Hello {$userName},\n\nYour document request (Request ID: {$docRequestId}) has been submitted successfully. We will notify you once the request is processed.\n\nThank you,\nYour App Name";

        $mail->send();
    } catch (Exception $mailException) {
        // Log the email sending error but continue (email failure should not rollback the main transaction)
        error_log("Confirmation email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }

    echo "Document Request Submitted Successfully.";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Submission Failed: " . $e->getMessage();
}
?>

<?php
session_start();

require "../config/dbconn.php"; 

// Create connection
$conn = new mysqli($servername, $dbUsername, $dbPassword, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Use transaction so that all inserts succeed or none do
$conn->begin_transaction();

try {
    /*********************** Insert Personal Details ***********************/
    // Prepare insert statement for Person table
    $personSql = "INSERT INTO Person 
        (first_name, middle_name, last_name, id_image_path, birth_date, gender, email, contact_number, marital_status, senior_or_pwd, solo_parent, emergency_contact_name, emergency_contact_number, emergency_contact_address, barangay_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($personSql);

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Retrieve values from form (step 1)
    // Note: For id_image_path, proper file-handling should be implemented. Here we use a placeholder.
    $firstName        = trim($_POST['firstName']);
    $middleName       = trim($_POST['middleName']);
    $lastName         = trim($_POST['lastName']);
    $idImagePath      = "uploads/placeholder.jpg"; // Replace with actual file upload logic.
    $birthDate        = $_POST['birthday'];
    $gender           = $_POST['gender'];
    $email            = $_POST['email'];
    $contactNumber    = $_POST['contactNumber'];
    $maritalStatus    = $_POST['maritalStatus'];
    $seniorOrPwd      = $_POST['seniorOrPwd'] ?: "None";  // Use "None" if not set.
    $soloParent       = $_POST['soloParent'] ?: "No";
    $emergencyName    = $_POST['emergencyName'];
    $emergencyNumber  = $_POST['emergencyNumber'];
    $emergencyAddress = $_POST['emergencyAddress'];
    // Assume the barangay drop-down sends an id
    $barangay_id     = intval($_POST['barangay']);

    $stmt->bind_param(
        "ssssssssssssssi",
        $firstName,
        $middleName,
        $lastName,
        $idImagePath,
        $birthDate,
        $gender,
        $email,
        $contactNumber,
        $maritalStatus,
        $seniorOrPwd,
        $soloParent,
        $emergencyName,
        $emergencyNumber,
        $emergencyAddress,
        $barangay_id
    );

    if (!$stmt->execute()) {
        throw new Exception("Person insert failed: " . $stmt->error);
    }

    // Get the generated person_id for later use
    $person_id = $stmt->insert_id;
    $stmt->close();

    /*********************** Insert Address Details ***********************/
    // Prepare insert statement for Address table (from step 2)
    $addressSql = "INSERT INTO Address 
        (person_id, residency_type, years_in_san_rafael, block_lot, phase, street, subdivision, city, province, barangay_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($addressSql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Retrieve Address form values
    $residencyType     = $_POST['residencyType'];
    $yearsInSanRafael  = intval($_POST['yearsInSanRafael']);
    $blockLot          = $_POST['blockLot'];
    $phase             = $_POST['phase'];
    $street            = $_POST['street'];
    $subdivision       = $_POST['subdivision'];
    $city              = "San Rafael"; // As defaulted in your form.
    $province          = "Bulacan";    // As defaulted.
    // For the address barangay, use the same provided barangay id.
    $addressBarangayId = intval($_POST['barangay']);

    $stmt->bind_param(
        "isissssssi",
        $person_id,
        $residencyType,
        $yearsInSanRafael,
        $blockLot,
        $phase,
        $street,
        $subdivision,
        $city,
        $province,
        $addressBarangayId
    );

    if (!$stmt->execute()) {
        throw new Exception("Address insert failed: " . $stmt->error);
    }
    $stmt->close();

    /*********************** Insert Document Request Details ***********************/
    // Prepare insert statement for DocumentRequest table (from step 3)
    $docReqSql = "INSERT INTO DocumentRequest 
        (person_id, document_type_id, request_date, status, remarks, clearance_purpose, residency_duration, residency_purpose, gmc_purpose, nic_reason, indigency_income, indigency_reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($docReqSql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Retrieve DocumentRequest form values
    $documentType = $_POST['documentType'];
    // Map the document type from the form to the corresponding document_type_id.
    // Adjust these values to match those in your DocumentType table.
    $documentTypeMapping = array(
        "barangayClearance"     => 1,
        "firstTimeJobSeeker"    => 2,
        "proofOfResidency"      => 3,
        "barangayIndigency"     => 4,
        "goodMoralCertificate"  => 5,
        "noIncomeCertification" => 6
    );
    if (!isset($documentTypeMapping[$documentType])) {
        throw new Exception("Invalid document type selected.");
    }
    $document_type_id = $documentTypeMapping[$documentType];
    $request_date     = date("Y-m-d H:i:s");
    $status           = "Pending"; // Default request status.
    $remarks          = "";        // Customize remarks if needed.

    // These document-specific fields are conditionally available based on the type.
    $clearance_purpose  = isset($_POST['purposeClearance']) ? $_POST['purposeClearance'] : null;
    $residency_duration = isset($_POST['residencyDuration']) ? $_POST['residencyDuration'] : null;
    $residency_purpose  = isset($_POST['residencyPurpose']) ? $_POST['residencyPurpose'] : null;
    $gmc_purpose        = isset($_POST['gmcPurpose']) ? $_POST['gmcPurpose'] : null;
    $nic_reason         = isset($_POST['nicReason']) ? $_POST['nicReason'] : null;
    $indigency_income   = isset($_POST['indigencyIncome']) ? $_POST['indigencyIncome'] : null;
    $indigency_reason   = isset($_POST['indigencyReason']) ? $_POST['indigencyReason'] : null;

    // Bind parameters for DocumentRequest.
    // Corrected binding string: two integers followed by ten strings (for the date, status, remarks, and document-specific fields),
    // one double for indigency_income, and a final string.
    $stmt->bind_param(
        "iissssssssds",
        $person_id,
        $document_type_id,
        $request_date,
        $status,
        $remarks,
        $clearance_purpose,
        $residency_duration,
        $residency_purpose,
        $gmc_purpose,
        $nic_reason,
        $indigency_income,
        $indigency_reason
    );

    if (!$stmt->execute()) {
        throw new Exception("Document Request insert failed: " . $stmt->error);
    }
    $stmt->close();

    // If all inserts work, commit the transaction.
    $conn->commit();

    echo "Document Request Submitted Successfully.";
} catch (Exception $e) {
    // Roll back the transaction if any error occurred.
    $conn->rollback();
    echo "Submission Failed: " . $e->getMessage();
}

// Close the database connection.
$conn->close();
?>
