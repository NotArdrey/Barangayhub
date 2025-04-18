<?php
session_start();
require "../config/dbconn.php"; // This file initializes the $pdo PDO connection

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/index.php");
    exit;
}

// Include PHPMailer library files (adjust the paths as needed)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

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
        (user_id, document_type_id, request_date, status, remarks, clearance_purpose, residency_duration, residency_purpose, gmc_purpose, nic_reason, indigency_income, indigency_reason, delivery_method)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($docReqSql);
    $stmt->execute([
        $userId,
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
        $indigency_reason,
        $deliveryMethod
    ]);
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
