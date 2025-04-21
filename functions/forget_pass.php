<?php
<<<<<<< HEAD
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
=======
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require "../config/dbconn.php";  // This file should define a valid $pdo instance.
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

/**
 * Audit Trail logging function.
 */
function logAuditTrail($pdo, $user_id, $action, $table_name = null, $record_id = null, $description = '') {
    $stmt = $pdo->prepare("INSERT INTO AuditTrail (admin_user_id, action, table_name, record_id, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $table_name, $record_id, $description]);
}

/**
 * Sends a password reset email to the user.
 *
 * @param string $email The user's email address.
 * @param PDO    $pdo   The PDO database connection.
 * @return string       A message indicating success or the error that occurred.
 */
function sendPasswordReset($email, $pdo) {
    // Validate the email.
>>>>>>> v5
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email address.";
    }
    
<<<<<<< HEAD
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
        $mail->Username   = 'SMTP_EMAIL';                     // Your SMTP username
        $mail->Password   = 'SMTP_PASSWORD';                               // Your SMTP password
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
=======
    // Look up the user in the database.
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return "No account is associated with this email.";
    }
    
    // Generate a secure reset token.
    $token = bin2hex(random_bytes(16));
    
    // Update the user's record with the reset token and expiry time (1 hour from now)
    $stmt = $pdo->prepare("UPDATE Users SET verification_token = ?, verification_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
    $stmt->execute([$token, $email]);
    
    // Create the password reset link.
    $resetLink = "https://localhost/barangayhub/pages/change_pass.php?email=" . urlencode($email) . "&token=" . $token;
    
    // Set up PHPMailer to send the password reset email.
    $mail = new PHPMailer(true);
    try {
        // SMTP configuration.
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'barangayhub2@gmail.com';  // Your SMTP username.
        $mail->Password   = 'eisy hpjz rdnt bwrp';         // Your SMTP password.
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        $mail->setFrom('noreply@barangayhub.com', 'Barangay Hub');
        $mail->addAddress($email);
        
        // Email content.
        $mail->isHTML(false);
        $mail->Subject = "Password Reset Request";
        $mail->Body    = "Dear User,\n\nWe received a request to reset your password. " .
                         "Please click the link below to reset your password:\n\n" .
                         $resetLink . "\n\n" .
                         "If you did not request a password reset, please ignore this email.";
        
        $mail->send();
        
        // Log the password reset request.
        logAuditTrail($pdo, $user['user_id'], "PASSWORD RESET REQUEST", "Users", $user['user_id'], "Password reset email sent to $email.");
        
>>>>>>> v5
        return "A password reset link has been sent to your email.";
    } catch (Exception $e) {
        return "Failed to send the password reset email. Please try again later. Mailer Error: {$mail->ErrorInfo}";
    }
}

// Process the form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
<<<<<<< HEAD
    $email = $_POST['email'];
    $message = sendPasswordReset($email);
=======
    $email = isset($_POST['email']) ? $_POST['email'] : '';
    $message = sendPasswordReset($email, $pdo);
>>>>>>> v5
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
<<<<<<< HEAD
                window.location.href = '../pages/change_pass.php';
=======
                window.location.href = '../pages/index.php';
>>>>>>> v5
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}
?>
