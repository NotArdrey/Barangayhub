<?php
session_start();
require "../config/dbconn.php"; // Include your database connection file

// Include PHPMailer classes
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form values
    $current_password = $_POST['current_password'];
    $new_password     = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate that the new passwords match
    if ($new_password !== $confirm_password) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Passwords do not match.'
                }).then(() => { window.history.back(); });
              </script>";
        exit;
    }

    // Retrieve the user's current password hash and email
    $query = "SELECT password, email FROM Users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || !password_verify($current_password, $user['password'])) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Current password is incorrect.'
                }).then(() => { window.history.back(); });
              </script>";
        exit;
    }

    // Hash the new password and update it in the database
    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $update_query = "UPDATE users SET password = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("si", $new_password_hash, $user_id);

    if ($update_stmt->execute()) {
        // Create a new PHPMailer instance and send confirmation email
        $mail = new PHPMailer(true);
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.example.com'; // Replace with your SMTP server
            $mail->SMTPAuth   = true;
            $mail->Username   = 'barangayhub2@gmail.com'; // Replace with your SMTP username
            $mail->Password   = 'eisy hpjz rdnt bwrp';       // Replace with your SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Recipients
            $mail->setFrom('noreply@barangayhub.com', 'Barangay Hub');
            $mail->addAddress($user['email']);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Password Change Confirmation';
            $mail->Body    = 'Your password has been successfully changed.';
            $mail->AltBody = 'Your password has been successfully changed.';

            $mail->send();
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Password updated successfully. A confirmation email has been sent to your email address.'
                    }).then(() => { window.location.href = '../pages/edit_account.php'; });
                  </script>";
        } catch (Exception $e) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Email Error',
                        text: \"Password updated, but the email could not be sent. Mailer Error: " . addslashes($mail->ErrorInfo) . "\"
                    }).then(() => { window.location.href = '../pages/edit_account.php'; });
                  </script>";
        }
    } else {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating password. Please try again.'
                }).then(() => { window.history.back(); });
              </script>";
    }
    exit;
}
