<?php
require "../config/dbconn.php";
session_start();

// Ensure that only a Barangay Admin (role_id=2) can access this file.
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../pages/index.php");
    exit;
}

// Retrieve the corresponding barangay_id from the Barangay table.
$barangayName = isset($_SESSION['barangay_name']) ? $_SESSION['barangay_name'] : "Undefined Barangay";
$stmt = $pdo->prepare("SELECT barangay_id FROM Barangay WHERE barangay_name = :barangay_name LIMIT 1");
$stmt->execute([':barangay_name' => $barangayName]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$barangay_id = $result ? $result['barangay_id'] : null;

// -------------------------
// (A) Handle Document Request Creation
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_request_submit'])) {
    // Retrieve and validate common form data.
    $documentType = $_POST['document_type'];
    $remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : "";

    // Capture additional conditional fields into an associative array.
    $additionalFields = [];
    if (!empty($_POST['clearance_purpose'])) {
        $additionalFields['clearance_purpose'] = $_POST['clearance_purpose'];
    }
    if (!empty($_POST['residency_duration'])) {
        $additionalFields['residency_duration'] = $_POST['residency_duration'];
    }
    if (!empty($_POST['residency_purpose'])) {
        $additionalFields['residency_purpose'] = $_POST['residency_purpose'];
    }
    if (!empty($_POST['gmc_purpose'])) {
        $additionalFields['gmc_purpose'] = $_POST['gmc_purpose'];
    }
    if (!empty($_POST['nic_reason'])) {
        $additionalFields['nic_reason'] = $_POST['nic_reason'];
    }
    if (!empty($_POST['indigency_income'])) {
        $additionalFields['indigency_income'] = $_POST['indigency_income'];
    }
    if (!empty($_POST['indigency_reason'])) {
        $additionalFields['indigency_reason'] = $_POST['indigency_reason'];
    }
    $additionalData = json_encode($additionalFields);

    // Determine the subject of the document:
    // If a person_id is provided, use it;
    // otherwise, insert a new person record with the provided details.
    if (empty($_POST['person_id'])) {
        // Insert new person details.
        // These form fields must be included in your document request form.
        $stmt = $pdo->prepare("INSERT INTO Person (first_name, middle_name, last_name, birth_date, gender, email, contact_number, marital_status, senior_or_pwd, solo_parent, emergency_contact_name, emergency_contact_number, emergency_contact_address, barangay_id)
                               VALUES (:first_name, :middle_name, :last_name, :birth_date, :gender, :email, :contact_number, :marital_status, :senior_or_pwd, :solo_parent, :emergency_contact_name, :emergency_contact_number, :emergency_contact_address, :barangay_id)");
        $stmt->execute([
            ':first_name'                => $_POST['person_first_name'],
            ':middle_name'               => isset($_POST['person_middle_name']) ? $_POST['person_middle_name'] : null,
            ':last_name'                 => $_POST['person_last_name'],
            ':birth_date'                => $_POST['person_birth_date'],
            ':gender'                    => $_POST['person_gender'],
            ':email'                     => $_POST['person_email'],
            ':contact_number'            => $_POST['person_contact_number'],
            ':marital_status'            => $_POST['person_marital_status'],
            ':senior_or_pwd'             => $_POST['person_senior_or_pwd'],
            ':solo_parent'               => $_POST['person_solo_parent'],
            ':emergency_contact_name'    => $_POST['person_emergency_contact_name'],
            ':emergency_contact_number'  => $_POST['person_emergency_contact_number'],
            ':emergency_contact_address' => $_POST['person_emergency_contact_address'],
            ':barangay_id'               => $barangay_id
        ]);
        $applicantPersonId = $pdo->lastInsertId();
    } else {
        $applicantPersonId = $_POST['person_id'];
    }

    // The requester is the current logged-in user.
    $requesterId = $_SESSION['user_id'];

    // Insert the new document request with a default status of "pending".
    $stmt = $pdo->prepare("INSERT INTO DocumentRequest (person_id, user_id, document_type_id, request_date, status, remarks, additional_data)
                           VALUES (:person_id, :user_id, :document_type_id, NOW(), 'pending', :remarks, :additional_data)");
    $stmt->execute([
         ':person_id'         => $applicantPersonId,
         ':user_id'           => $requesterId,
         ':document_type_id'  => $documentType,
         ':remarks'           => $remarks,
         ':additional_data'   => $additionalData
    ]);
    $newRequestId = $pdo->lastInsertId();

    // Log the action in AuditTrail.
    $auditStmt = $pdo->prepare("INSERT INTO AuditTrail (admin_user_id, action, table_name, record_id, description)
                                VALUES (:admin_user_id, 'Add', 'DocumentRequest', :record_id, :description)");
    $auditStmt->execute([
         ':admin_user_id' => $_SESSION['user_id'],
         ':record_id'     => $newRequestId,
         ':description'   => "Added new document request with ID " . $newRequestId
    ]);
    
    header("Location: ../pages/barangay_admin_dashboard.php#docRequests");
    exit;
}

// -------------------------
// (B) Mark a Document Request as Complete
// -------------------------
if (isset($_GET['action']) && $_GET['action'] === 'complete_doc_request' && isset($_GET['id'])) {
    $docRequestId = $_GET['id'];
    $stmt = $pdo->prepare("UPDATE DocumentRequest SET status = 'complete' WHERE document_request_id = :docRequestId");
    $stmt->execute([':docRequestId' => $docRequestId]);
    
    // Log the update in AuditTrail.
    $auditStmt = $pdo->prepare("INSERT INTO AuditTrail (admin_user_id, action, table_name, record_id, description)
                                VALUES (:admin_user_id, 'Update', 'DocumentRequest', :record_id, :description)");
    $auditStmt->execute([
         ':admin_user_id' => $_SESSION['user_id'],
         ':record_id'     => $docRequestId,
         ':description'   => "Marked document request ID " . $docRequestId . " as complete."
    ]);
    
    header("Location: ../pages/barangay_admin_dashboard.php#docRequests");
    exit;
}

// -------------------------
// (C) Delete a Document Request
// -------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete_doc_request' && isset($_GET['id'])) {
    $docRequestId = $_GET['id'];
    $stmt = $pdo->prepare("DELETE FROM DocumentRequest WHERE document_request_id = :docRequestId");
    $stmt->execute([':docRequestId' => $docRequestId]);
    
    // Log the deletion in AuditTrail.
    $auditStmt = $pdo->prepare("INSERT INTO AuditTrail (admin_user_id, action, table_name, record_id, description)
                                VALUES (:admin_user_id, 'Delete', 'DocumentRequest', :record_id, :description)");
    $auditStmt->execute([
         ':admin_user_id' => $_SESSION['user_id'],
         ':record_id'     => $docRequestId,
         ':description'   => "Deleted document request ID " . $docRequestId
    ]);
    
    header("Location: ../pages/barangay_admin_dashboard.php#docRequests");
    exit;
}
?>
