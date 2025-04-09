<?php
// reset_password.php

session_start();

// Import PHPMailer classes into the global namespace.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader.
require '../vendor/autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

 



/**
 * Simulate token validation.
 * Replace this with your actual database verification.
 */
function validateToken($email, $token) {
    // For demonstration, assume the token is valid if both fields are not empty.
    return !empty($email) && !empty($token);
}

$error = "";
$success = "";

// Check if the incoming request is POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // If the request contains the "resend" field, process as a resend email request.
    if (isset($_POST['resend'])) {
        $email = $_POST['email'] ?? '';

        // Validate the email address.
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address.";
        } else {
            // Generate a new secure token.
            $token = bin2hex(random_bytes(16));
            
            // Save the new token and timestamp to the database here.
            // For demonstration, we simulate the process.

            // Construct the password reset link.
            $resetLink = "https://localhost/barangayhub/pages/reset_password.php?email=" 
                         . urlencode($email) . "&token=" . $token;
            
            // Prepare and send the email.
            $mail = new PHPMailer(true);
            try {
                // SMTP server configuration.
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'SMTP_EMAIL';
                $mail->Password   = 'SMTP_PASSWORD';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                
                // Set sender and recipient.
                $mail->setFrom('noreply@barangayhub.com', 'Barangay Hub');
                $mail->addAddress($email);
                
                // Email content.
                $mail->isHTML(false);
                $mail->Subject = "Password Reset Request";
                $mail->Body    = "Dear User,\n\nWe received a request to reset your password. "
                               . "Please click the link below to reset your password:\n\n"
                               . $resetLink . "\n\n"
                               . "If you did not request a password reset, please ignore this email.";
                
                // Attempt to send the email.
                $mail->send();
                $success = "A new password reset email has been sent to your email.";
            } catch (Exception $e) {
                $error = "Failed to send the password reset email. Please try again later. Mailer Error: {$mail->ErrorInfo}";
            }
        }
        // Display SweetAlert and then redirect back to the reset page.
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Password Reset Resend</title>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
            <script>
                Swal.fire({
                    icon: <?php echo json_encode(empty($error) ? "success" : "error"); ?>,
                    title: <?php echo json_encode(empty($error) ? "Success" : "Error"); ?>,
                    text: <?php echo json_encode(empty($error) ? $success : $error); ?>
                }).then(() => {
                    // Redirect back to the reset page (or another appropriate page).
                    window.location.href = 'reset_password.php?email=' + <?php echo json_encode($email); ?>;
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    // Otherwise, process the password update submission.
    else {
        $email           = $_POST['email'] ?? '';
        $token           = $_POST['token'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
    
        if ($newPassword !== $confirmPassword) {
            $error = "Passwords do not match.";
        } elseif (!validateToken($email, $token)) {
            $error = "Invalid or expired token.";
        } else {
            // Hash the new password.
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    
            // Update the password in your database here.
            // Example: UPDATE users SET password='$hashedPassword' WHERE email='$email' AND token='$token'
            // For demonstration, we'll assume the update is successful.
            $success = "Your password has been changed successfully.";
        }
        ?>
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <title>Password Reset</title>
          <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
          <script>
            <?php if (!empty($error)) { ?>
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: <?php echo json_encode($error); ?>
              }).then(() => {
                window.history.back();
              });
            <?php } else { ?>
              Swal.fire({
                icon: 'success',
                title: 'Success',
                text: <?php echo json_encode($success); ?>
              }).then(() => {
                window.location.href = 'login.php';
              });
            <?php } ?>
          </script>
        </body>
        </html>
        <?php
        exit();
    }
} else {
    // For GET requests, retrieve the email and token from the URL parameters.
    $email = $_GET['email'] ?? '';
    $token = $_GET['token'] ?? '';
}
?>