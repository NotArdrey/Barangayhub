<?php
// Import PHPMailer classes into the global namespace.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Load Composer's autoloader or adjust path if needed.
require '../vendor/autoload.php';

function sendPasswordReset($email) {
    // Validate email format.
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email address.";
    }
    
    // Simulate checking if the user exists in the database.
    // Replace this with your actual database lookup.
    $userExists = true; // Assume user exists for demonstration.
    
    if (!$userExists) {
        return "No account is associated with this email.";
    }
    
    // Generate a secure unique token.
    $token = bin2hex(random_bytes(16));
    
    // Save the token and the current timestamp to the database here.
    // For production, update your user table with the token and timestamp for later verification.
    
    // Construct the password reset link.
    $resetLink = "https://localhost/barangayhub/pages/change_pass.php?email=" . urlencode($email) . "&token=" . $token;
    
    // Create a new PHPMailer instance.
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();                                            // Send using SMTP
        $mail->Host       = 'smtp.gmail.com';                     // Set your SMTP server address
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = 'barangayhub2@gmail.com';                     // Your SMTP username
        $mail->Password   = 'eisy hpjz rdnt bwrp';                               // Your SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption
        $mail->Port       = 587;                                    // TCP port to connect to
        
        // Recipients
        $mail->setFrom('noreply@barangayhub.com', 'Mailer');
        $mail->addAddress($email);     // Add the recipient
        
        // Email content
        $mail->isHTML(false);                                  // Set email format to plain text
        $mail->Subject = "Password Reset Request";
        $mail->Body    = "Dear User,\n\nWe received a request to reset your password. "
                       . "Please click the link below to reset your password:\n\n"
                       . $resetLink . "\n\n"
                       . "If you did not request a password reset, please ignore this email.";
        
        // Send the email.
        $mail->send();
        return "A password reset link has been sent to your email.";
    } catch (Exception $e) {
        return "Failed to send the password reset email. Please try again later. Mailer Error: {$mail->ErrorInfo}";
    }
}

// Process the form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $message = sendPasswordReset($email);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Password Reset</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: <?php echo json_encode((strpos($message, 'sent') !== false) ? 'success' : 'error'); ?>,
                title: 'Password Reset',
                text: <?php echo json_encode($message); ?>
            }).then(() => {
                window.location.href = '../pages/change_pass.php';
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>
