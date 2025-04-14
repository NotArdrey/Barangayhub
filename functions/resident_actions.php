<?php
// Residents Actions Code

require "../config/dbconn.php";
session_start();

// ADD NEW RESIDENT
if (isset($_POST['add_resident_submit'])) {
    // Add resident logic here: Insert into Person first, then insert into Address with the new person_id.
    echo "<script>alert('Resident added successfully.'); window.location.href='../pages/barangay_admin_dashboard.php#residents';</script>";
    exit;
}

// UPDATE EXISTING RESIDENT
if (isset($_POST['edit_resident_submit'])) {
    // Update the Person table
    $stmt = $pdo->prepare("UPDATE Person SET
        first_name = :first_name,
        middle_name = :middle_name,
        last_name = :last_name,
        birth_date = :birth_date,
        gender = :gender,
        email = :email,
        contact_number = :contact_number,
        marital_status = :marital_status,
        senior_or_pwd = :senior_or_pwd,
        solo_parent = :solo_parent,
        emergency_contact_name = :emergency_contact_name,
        emergency_contact_number = :emergency_contact_number,
        emergency_contact_address = :emergency_contact_address
        WHERE person_id = :person_id");

    $stmt->execute([
        ':first_name' => $_POST['edit_first_name'],
        ':middle_name' => $_POST['edit_middle_name'],
        ':last_name' => $_POST['edit_last_name'],
        ':birth_date' => $_POST['edit_birth_date'],
        ':gender' => $_POST['edit_gender'],
        ':email' => $_POST['edit_email'],
        ':contact_number' => $_POST['edit_contact_number'],
        ':marital_status' => $_POST['edit_marital_status'],
        ':senior_or_pwd' => $_POST['edit_senior_or_pwd'],
        ':solo_parent' => $_POST['edit_solo_parent'],
        ':emergency_contact_name' => $_POST['edit_emergency_contact_name'],
        ':emergency_contact_number' => $_POST['edit_emergency_contact_number'],
        ':emergency_contact_address' => $_POST['edit_emergency_contact_address'],
        ':person_id' => $_POST['edit_person_id']
    ]);

    // Update the corresponding Address record
    $stmt = $pdo->prepare("UPDATE Address SET
        residency_type = :residency_type,
        years_in_san_rafael = :years_in_san_rafael,
        block_lot = :block_lot,
        phase = :phase,
        street = :street,
        subdivision = :subdivision
        WHERE person_id = :person_id");
    $stmt->execute([
        ':residency_type' => $_POST['edit_residency_type'],
        ':years_in_san_rafael' => $_POST['edit_years_in_san_rafael'],
        ':block_lot' => $_POST['edit_block_lot'],
        ':phase' => $_POST['edit_phase'],
        ':street' => $_POST['edit_street'],
        ':subdivision' => $_POST['edit_subdivision'],
        ':person_id' => $_POST['edit_person_id']
    ]);

    // Process government ID file upload if provided.
    if (isset($_FILES['edit_government_id']) && $_FILES['edit_government_id']['error'] == 0) {
        $target_dir = "../uploads/government_ids/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . basename($_FILES["edit_government_id"]["name"]);
        if(move_uploaded_file($_FILES["edit_government_id"]["tmp_name"], $target_file)) {
            // Update the government ID in the Users table (stored in id_image_path)
            $stmt = $pdo->prepare("UPDATE Users SET id_image_path = :government_id WHERE email = :email AND role_id = 3");
            $stmt->execute([
                ':government_id' => $target_file,
                ':email' => $_POST['edit_email']
            ]);
        }
    }

    echo "<script>alert('Resident updated successfully.'); window.location.href='../pages/barangay_admin_dashboard.php#residents';</script>";
    exit;
}

// DELETE RESIDENT
if (isset($_GET['delete_resident'])) {
    // Retrieve the resident's email from Person using person_id
    $stmt = $pdo->prepare("SELECT email FROM Person WHERE person_id = :person_id");
    $stmt->execute([':person_id' => $_GET['delete_resident']]);
    $person = $stmt->fetch(PDO::FETCH_ASSOC);

    // Delete the related Address record
    $stmt = $pdo->prepare("DELETE FROM Address WHERE person_id = :person_id");
    $stmt->execute([':person_id' => $_GET['delete_resident']]);

    // Delete the Person record
    $stmt = $pdo->prepare("DELETE FROM Person WHERE person_id = :person_id");
    $stmt->execute([':person_id' => $_GET['delete_resident']]);

    // If the person exists, delete their Users record (for role_id 3 - Resident)
    if ($person && isset($person['email'])) {
        $stmt = $pdo->prepare("DELETE FROM Users WHERE email = :email AND role_id = 3");
        $stmt->execute([':email' => $person['email']]);
    }

    echo "<script>alert('Resident deleted successfully.'); window.location.href='../pages/barangay_admin_dashboard.php#residents';</script>";
    exit;
}
?>