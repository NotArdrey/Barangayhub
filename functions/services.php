<?php
/**
 * Document Request Processing Script
 * 
 * Handles the submission of document requests from services.php
 * Validates input, processes files, and updates database
 */
session_start();
require_once "../config/dbconn.php";
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Set content type to JSON for all responses
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}
define('PAYMONGO_SECRET_KEY', '');
function getDocumentFee(int $docTypeId): int {
    return [
        1 => 50, 2 => 30, 3 => 30,
        4 => 40, 5 => 20, 6 => 20
    ][$docTypeId] ?? 50;
}
function createPayMongoLink(int $amountPesos, string $desc, string $succ, string $fail): string {
    $payload = [
        'data'=>[
          'attributes'=>[
            'amount'      => $amountPesos*100,
            'currency'    => 'PHP',
            'description' => $desc,
            'redirect'    => ['success'=>$succ,'failed'=>$fail]
          ]
        ]
    ];
    $ch = curl_init('https://api.paymongo.com/v1/payment_links');
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_POST=>true,
      CURLOPT_USERPWD=>PAYMONGO_SECRET_KEY.':',
      CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
      CURLOPT_POSTFIELDS=>json_encode($payload)
    ]);
    $resp = curl_exec($ch);
    if (curl_errno($ch)) throw new Exception(curl_error($ch));
    $d = json_decode($resp,true);
    if (!empty($d['errors'])) throw new Exception(json_encode($d['errors']));
    return $d['data']['attributes']['url'];
}

// Get user data from session
$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['user_email'] ?? '';
$userName = $_SESSION['user_name'] ?? 'User';

/**
 * Process the document request
 */
try {
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Validate file upload
    validateFileUpload();
    
    // 2. Save uploaded file
    $dbImagePath = saveUploadedFile($userId);
    
    // 3. Update user record with ID image path
    updateUserIdImage($userId, $dbImagePath);
    
    // 4. Get barangay information
    $barangayId = intval($_POST['barangay_id']);
    $barangayInfo = getBarangayInfo($barangayId);
    
    // 5. Process document request
    $docTypeId = getDocumentTypeId($_POST['documentType'] ?? '');
    $deliveryMethod = $_POST['deliveryMethod'] ?? 'Hardcopy';
    
    // 6. Insert document request
    $requestId = insertDocumentRequest($userId, $docTypeId, $barangayId, $deliveryMethod);
    
    // 7. Insert document attributes
    insertDocumentAttributes($requestId);
    
    // 8. Add audit log
    addAuditLog($userId, $requestId);
    
    // 9. Determine processing message based on time
    $processingMessage = getProcessingMessage($barangayInfo);
    
    // 10. Commit transaction
    $pdo->commit();
    if ($deliveryMethod === 'Softcopy') {
        $fee    = getDocumentFee($docTypeId);
        $desc   = "Payment for request #{$requestId}";
        $succ   = "https://your-domain.com/pages/payment_success.php?req={$requestId}";
        $fail   = "https://your-domain.com/pages/payment_failed.php?req={$requestId}";
        $link   = createPayMongoLink($fee, $desc, $succ, $fail);
  
        header("Location: $link");
        exit;
    }
  
    // 11. Send confirmation email
    sendConfirmationEmail($userEmail, $userName, $requestId, $processingMessage);
    
    // 12. Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Document request submitted successfully!',
        'processing_message' => $processingMessage
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log the error
    error_log("Document request error: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
exit;

/**
 * Helper Functions
 */

/**
 * Validate uploaded file
 */
function validateFileUpload() {
    if (!isset($_FILES['uploadId']) || $_FILES['uploadId']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("ID file upload failed");
    }
    
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $fileExtension = strtolower(pathinfo($_FILES['uploadId']['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception("Invalid file format. Only JPG, PNG, PDF allowed.");
    }
    
    if ($_FILES['uploadId']['size'] > 2 * 1024 * 1024) {
        throw new Exception("File too large. Max 2MB allowed.");
    }
}

/**
 * Save uploaded file
 */
function saveUploadedFile($userId) {
    $uploadDir = __DIR__ . '/../uploads/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception("Failed to create upload directory");
        }
    }
    
    // Generate unique filename
    $fileExtension = strtolower(pathinfo($_FILES['uploadId']['name'], PATHINFO_EXTENSION));
    $idImageName = "user_{$userId}_id_" . time() . ".{$fileExtension}";
    $idImagePath = $uploadDir . $idImageName;
    
    // Move uploaded file
    if (!move_uploaded_file($_FILES['uploadId']['tmp_name'], $idImagePath)) {
        throw new Exception("Failed to save uploaded file");
    }
    
    return "uploads/{$idImageName}";
}

/**
 * Update user ID image in database
 */
function updateUserIdImage($userId, $dbImagePath) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE Users SET id_image_path = ? WHERE user_id = ?");
    if (!$stmt->execute([$dbImagePath, $userId])) {
        throw new Exception("Failed to update user record");
    }
}

/**
 * Get barangay information
 */
function getBarangayInfo($barangayId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT cutoff_time, opening_time, closing_time 
        FROM Barangay 
        WHERE barangay_id = ?
    ");
    $stmt->execute([$barangayId]);
    $barangayInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$barangayInfo) {
        throw new Exception("Invalid barangay selected");
    }
    
    return $barangayInfo;
}

/**
 * Get document type ID
 */
function getDocumentTypeId($documentType) {
    $documentTypeMap = [
        'barangayClearance' => 1,
        'firstTimeJobSeeker' => 2,
        'proofOfResidency' => 3,
        'barangayIndigency' => 4,
        'goodMoralCertificate' => 5,
        'noIncomeCertification' => 6
    ];
    
    if (!isset($documentTypeMap[$documentType])) {
        throw new Exception("Invalid document type");
    }
    
    return $documentTypeMap[$documentType];
}

/**
 * Insert document request
 */
function insertDocumentRequest($userId, $docTypeId, $barangayId, $deliveryMethod) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO DocumentRequest 
        (user_id, document_type_id, barangay_id, request_date, status, delivery_method)
        VALUES (?, ?, ?, NOW(), 'Pending', ?)
    ");
    
    if (!$stmt->execute([$userId, $docTypeId, $barangayId, $deliveryMethod])) {
        throw new Exception("Failed to insert document request");
    }
    
    return $pdo->lastInsertId();
}

/**
 * Insert document attributes
 */
function insertDocumentAttributes($requestId) {
    global $pdo;
    
    $attributes = [
        'clearance_purpose' => $_POST['purposeClearance'] ?? null,
        'residency_duration' => $_POST['residencyDuration'] ?? null,
        'residency_purpose' => $_POST['residencyPurpose'] ?? null,
        'gmc_purpose' => $_POST['gmcPurpose'] ?? null,
        'nic_reason' => $_POST['nicReason'] ?? null,
        'indigency_income' => $_POST['indigencyIncome'] ?? null,
        'indigency_reason' => $_POST['indigencyReason'] ?? null
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO DocumentRequestAttribute (request_id, attr_key, attr_value)
        VALUES (?, ?, ?)
    ");
    
    foreach ($attributes as $key => $value) {
        if ($value !== null && trim($value) !== '') {
            if (!$stmt->execute([$requestId, $key, trim($value)])) {
                throw new Exception("Failed to insert document attribute: $key");
            }
        }
    }
}

/**
 * Add audit log entry
 */
function addAuditLog($userId, $requestId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO AuditTrail 
        (admin_user_id, action, table_name, record_id, description)
        VALUES (?, 'INSERT', 'DocumentRequest', ?, ?)
    ");
    
    if (!$stmt->execute([$userId, $requestId, "Submitted document request and uploaded ID"])) {
        throw new Exception("Failed to record audit log");
    }
}

/**
 * Determine processing message based on time
 */
function getProcessingMessage($barangayInfo) {
    $currentTime = date('H:i:s');
    $currentDay = date('N'); // 1 (Monday) to 7 (Sunday)
    
    $cutoffTime = strtotime($barangayInfo['cutoff_time']);
    $openingTime = strtotime($barangayInfo['opening_time']);
    $closingTime = strtotime($barangayInfo['closing_time']);
    $currentTimestamp = strtotime($currentTime);
    
    $isWeekend = ($currentDay >= 6); // Saturday or Sunday
    $afterCutoff = ($currentTimestamp > $cutoffTime);
    $beforeOpening = ($currentTimestamp < $openingTime);
    $afterClosing = ($currentTimestamp > $closingTime);
    
    if ($isWeekend || $afterCutoff || $beforeOpening || $afterClosing) {
        return "Your request was received outside business hours; it will be processed on the next business day.";
    } else {
        return "Your request will be processed today.";
    }
}

/**
 * Send confirmation email
 */
function sendConfirmationEmail($userEmail, $userName, $requestId, $processingMessage) {
    if (empty($userEmail)) {
        return; // Skip email if no email address
    }
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.example.com'; // Update with actual SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'barangayhub2@gmail.com'; // Update with actual username
        $mail->Password = 'eisy hpjz rdnt bwrp'; // Use app-specific password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('noreply@barangayhub.com', 'Barangay Hub');
        $mail->addAddress($userEmail, $userName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Document Request Confirmation';
        
        // Email body
        $body = "
        <html>
        <body>
            <h2>Document Request Confirmation</h2>
            <p>Hello {$userName},</p>
            <p>Your document request (Reference #: {$requestId}) has been successfully submitted.</p>
            <p>{$processingMessage}</p>
            <p>Please wait for an email with further details regarding your request.</p>
            <p>Thank you for using Barangay Hub services.</p>
            <hr>
            <p><small>This is an automated message. Please do not reply to this email.</small></p>
        </body>
        </html>
        ";
        
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body); // Plain text version
        
        $mail->send();
    } catch (Exception $e) {
        // Log email error but don't stop execution
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
    }
}