<?php
session_start();
require "../config/dbconn.php"; // This file should define $dsn, $user, $pass

// Load PHPMailer classes via Composer autoload
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require '../vendor/autoload.php';

require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();



if (isset($_GET['token'])) {
    // ---------------------------
    // Email Verification Process
    // ---------------------------
    $token = $_GET['token'];

    try {
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if a user exists with this token, is not yet verified,
        // and that the verification token has not expired.
        $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE verification_token = ? AND isverify = 'no' AND verification_expiry > NOW()");
        $stmt->execute([$token]);

        if ($stmt->rowCount() > 0) {
            // Mark the user as verified and clear the token and expiry fields
            $stmt = $pdo->prepare("UPDATE Users SET isverify = 'yes', verification_token = NULL, verification_expiry = NULL WHERE verification_token = ?");
            $stmt->execute([$token]);
            $message = "Your email has been verified successfully!";
            $icon = "success";
        } else {
            $message = "Invalid or expired verification token.";
            $icon = "error";
        }
    } catch (PDOException $e) {
        $message = "Database error: " . $e->getMessage();
        $icon = "error";
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
      <meta charset="UTF-8">
      <title>Email Verification</title>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
      <script>
        Swal.fire({
          icon: <?php echo json_encode($icon); ?>,
          title: <?php echo json_encode(($icon === "success") ? "Verified" : "Error"); ?>,
          text: <?php echo json_encode($message); ?>
        }).then(() => {
          window.location.href = "../pages/index.php";
        });
      </script>
    </body>
    </html>
    <?php
    exit();
} elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    // ---------------------------
    // Registration Process
    // ---------------------------
    // Retrieve and trim form inputs
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    // Retrieve role_id from the form (hidden input)
    $role_id = isset($_POST['role_id']) ? trim($_POST['role_id']) : '';

    $errors = [];

    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Validate password
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    // Validate confirm password
    if (empty($confirmPassword)) {
        $errors[] = "Please confirm your password.";
    } elseif ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    // Optionally validate role_id if needed, e.g.:
    if (empty($role_id)) {
        $errors[] = "Role is not specified.";
    }
    
    // Proceed if no validation errors
    if (empty($errors)) {
        // Hash the password securely
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate a verification token (32 hexadecimal characters)
        $verificationToken = bin2hex(random_bytes(16));
        // Set token expiry to 24 hours from now (adjust as needed)
        $verificationExpiry = date('Y-m-d H:i:s', strtotime('+1 day'));

        try {
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Check if the email is already registered
            $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "This email is already registered.";
            } else {
                // Use email as default username (update logic as needed)
                $username = $email;
                // Insert the new user record with isverify set to "no" and include role_id
                $stmt = $pdo->prepare("INSERT INTO Users (username, email, password_hash, role_id, isverify, verification_token, verification_expiry) VALUES (?, ?, ?, ?, 'no', ?, ?)");
                $stmt->execute([$username, $email, $passwordHash, $role_id, $verificationToken, $verificationExpiry]);

                // Create a verification link (adjust the URL to your domain/path)
                $verificationLink = "https://localhost/barangayhub/pages/verify.php?token=" . $verificationToken;

                // Send the verification email using PHPMailer
                $mail = new PHPMailer(true);
                try {
                    // SMTP server configuration
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'barangayhub2@gmail.com';  // Your SMTP username
                    $mail->Password   = 'eisy hpjz rdnt bwrp';         // Your SMTP password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    // Recipients
                    $mail->setFrom('noreply@barangayhub.com', 'Barangay Hub');
                    $mail->addAddress($email);

                    // Email content
                    $mail->isHTML(true);
                    $mail->Subject = 'Email Verification';
                    $mail->Body    = "Thank you for registering. Please verify your email by clicking the following link: <a href='$verificationLink'>$verificationLink</a><br>Your link will expire in 24 hours.";

                    $mail->send();
                    $message = "Registration successful! Please check your email to verify your account.";
                    $icon = "success";
                    $redirectUrl = "../pages/index.php";
                } catch (Exception $e) {
                    $errors[] = "Message could not be sent. Mailer Error: " . $mail->ErrorInfo;
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        // There were errors during registration
        $errorMessage = implode("\n", $errors);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <title>Registration Error</title>
          <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
          <script>
            Swal.fire({
              icon: "error",
              title: "Error",
              text: <?php echo json_encode($errorMessage); ?>
            }).then(() => {
              window.location.href = "../pages/register.php";
            });
          </script>
        </body>
        </html>
        <?php
        exit();
    } else {
        // Registration successful and email sent
        ?>
        <!DOCTYPE html>
        <html>
        <head>
          <meta charset="UTF-8">
          <title>Registration Success</title>
          <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </head>
        <body>
          <script>
            Swal.fire({
              icon: "success",
              title: "Success",
              text: <?php echo json_encode($message); ?>
            }).then(() => {
              window.location.href = <?php echo json_encode($redirectUrl); ?>;
            });
          </script>
        </body>
        </html>
        <?php
        exit();
    }
} else {
    // If no token and not a POST request, redirect to the registration page
    header("Location: ../pages/register.php");
    exit();
}
?>
