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
