<?php
session_start();
require "../config/dbconn.php"; 

// Ensure user is logged in (i.e. is a document requester)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/index.php");
    exit;
}

// Create connection
$conn = new mysqli($servername, $dbUsername, $dbPassword, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process file upload for government ID image (for the certificate subject)
$idImagePath = "";
if (isset($_FILES['uploadId']) && $_FILES['uploadId']['error'] == UPLOAD_ERR_OK) {
    $uploadDir = "uploads/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    $fileName = uniqid() . '_' . basename($_FILES['uploadId']['name']);
    $targetFilePath = $uploadDir . $fileName;
    if (move_uploaded_file($_FILES['uploadId']['tmp_name'], $targetFilePath)) {
        $idImagePath = $targetFilePath;
    } else {
        die("Error: Failed to move uploaded file.");
    }
} else {
    die("Error: File upload error (" . $_FILES['uploadId']['error'] . ").");
}

// Begin transaction to ensure all inserts succeed or none do
$conn->begin_transaction();

try {
    /***************** Insert Certificate Subject into Person Table *****************/
    // Retrieve certificate subject information from form inputs
    $firstName   = trim($_POST['firstName']);
    $middleName  = trim($_POST['middleName']);
    $lastName    = trim($_POST['lastName']);
    $birthDate   = $_POST['birthday'];
    $gender      = $_POST['gender'];
    $email       = $_POST['email'];
    $contactNumber = $_POST['contactNumber'];
    $maritalStatus = $_POST['maritalStatus'];
    $seniorOrPwd   = isset($_POST['seniorOrPwd']) ? $_POST['seniorOrPwd'] : "None";
    $soloParent    = isset($_POST['soloParent']) ? $_POST['soloParent'] : "No";
    $emergencyName = $_POST['emergencyName'];
    $emergencyNumber = $_POST['emergencyNumber'];
    $emergencyAddress = $_POST['emergencyAddress'];
    $barangay_id   = intval($_POST['barangay']);
    
    // Insert into Person table (certificate subject details)
    $personSql = "INSERT INTO Person 
        (first_name, middle_name, last_name, id_image_path, birth_date, gender, email, contact_number, marital_status, senior_or_pwd, solo_parent, emergency_contact_name, emergency_contact_number, emergency_contact_address, barangay_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($personSql);
    if (!$stmt) {
        throw new Exception("Prepare failed (Person): " . $conn->error);
    }
    
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
    
    // Get the newly generated person_id for later use in Address and DocumentRequest
    $person_id = $stmt->insert_id;
    $stmt->close();
    
    /***************** Insert Address Details *****************/
    $addressSql = "INSERT INTO Address 
        (person_id, residency_type, years_in_san_rafael, block_lot, phase, street, subdivision, city, province, barangay_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($addressSql);
    if (!$stmt) {
        throw new Exception("Prepare failed (Address): " . $conn->error);
    }
    
    $residencyType    = $_POST['residencyType'];
    $yearsInSanRafael = intval($_POST['yearsInSanRafael']);
    $blockLot         = $_POST['blockLot'];
    $phase            = $_POST['phase'];
    $street           = $_POST['street'];
    $subdivision      = $_POST['subdivision'];
    $city             = "San Rafael";
    $province         = "Bulacan";
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
    
    /***************** Insert Document Request Details *****************/
    // Retrieve the requester’s user ID from the session
    $user_id = $_SESSION['user_id'];
    
    // Map document type text to document type ID (ensure your DocumentType table contains these values)
    $documentType = $_POST['documentType'];
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
    $status           = "Pending";
    $remarks          = "";
    $clearance_purpose  = isset($_POST['purposeClearance']) ? $_POST['purposeClearance'] : null;
    $residency_duration = isset($_POST['residencyDuration']) ? $_POST['residencyDuration'] : null;
    $residency_purpose  = isset($_POST['residencyPurpose']) ? $_POST['residencyPurpose'] : null;
    $gmc_purpose        = isset($_POST['gmcPurpose']) ? $_POST['gmcPurpose'] : null;
    $nic_reason         = isset($_POST['nicReason']) ? $_POST['nicReason'] : null;
    $indigency_income   = isset($_POST['indigencyIncome']) ? $_POST['indigencyIncome'] : null;
    $indigency_reason   = isset($_POST['indigencyReason']) ? $_POST['indigencyReason'] : null;
    
    // Updated query includes user_id to link the document request to the requester
    $docReqSql = "INSERT INTO DocumentRequest 
        (person_id, user_id, document_type_id, request_date, status, remarks, clearance_purpose, residency_duration, residency_purpose, gmc_purpose, nic_reason, indigency_income, indigency_reason)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($docReqSql);
    if (!$stmt) {
        throw new Exception("Prepare failed (DocumentRequest): " . $conn->error);
    }
    
    // Build the bind type string: we need three integers (person_id, user_id, document_type_id),
    // then 8 strings for request_date, status, remarks, clearance_purpose, residency_duration, residency_purpose, gmc_purpose, nic_reason,
    // then a double for indigency_income and a string for indigency_reason.
    // That totals 3 (i) + 8 (s) + 1 (d) + 1 (s) = 13 parameters.
    // To be safe, we concatenate the type string using str_repeat:
    $types = "iii" . str_repeat("s", 8) . "ds"; // equals: "iii" + "ssssssss" + "d" + "s"
    
    $stmt->bind_param(
        $types,
        $person_id,
        $user_id,
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
    
    // Commit the transaction if everything is successful
    $conn->commit();
    echo "Document Request Submitted Successfully.";
    
} catch (Exception $e) {
    // Roll back the transaction if any error occurs.
    $conn->rollback();
    echo "Submission Failed: " . $e->getMessage();
}

$conn->close();
?>
d