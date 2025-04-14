<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Include PDO connection (defined in dbconn.php as $pdo)
require "../config/dbconn.php";

// ------------------------------------------------------
// Email Verification: Process link click with token
// ------------------------------------------------------
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Prepare statement using PDO to find a user with the provided token,
    // who is not verified and whose token has not expired.
    $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE verification_token = :token AND isverify = 'no' AND verification_expiry > NOW()");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $user_id = $user['user_id'];
        
        // Update the user record to mark as verified and clear token data.
        $stmt2 = $pdo->prepare("UPDATE Users SET isverify = 'yes', verification_token = NULL, verification_expiry = NULL WHERE verification_token = :token");
        if (!$stmt2->execute([':token' => $token])) {
            $message = "Update failed.";
            $icon = "error";
        } else {
            // Retrieve the user's email address
            $stmt3 = $pdo->prepare("SELECT email FROM Users WHERE user_id = :user_id");
            $stmt3->execute([':user_id' => $user_id]);
            $userEmail = $stmt3->fetchColumn();

            // Prepare and send confirmation email to the user using PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'barangayhub2@gmail.com';
                $mail->Password   = 'eisy hpjz rdnt bwrp';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('noreply@barangayhub.com', 'Barangay Hub');
                $mail->addAddress($userEmail);

                $mail->isHTML(true);
                $mail->Subject = 'Registration Completed';
                $mail->Body    = 'Congratulations! Your email has been verified and your account is now active. You have been registered successfully.';
                $mail->AltBody = 'Congratulations! Your email has been verified and your account is now active.';
                $mail->send();
            } catch (Exception $e) {
                // Log the error if needed, but do not block verification.
            }
            $message = "Your email has been verified successfully!";
            $icon = "success";
        }
    } else {
        $message = "Invalid or expired verification token.";
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
}

// ------------------------------------------------------
// Registration Process: Creating a New User Account
// ------------------------------------------------------
elseif ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and trim form inputs
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
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

    // Confirm password check
    if (empty($confirmPassword)) {
        $errors[] = "Please confirm your password.";
    } elseif ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    // Set role_id for residents (every new user gets role_id = 3)
    $role_id = 3;

    if (empty($errors)) {
        // Hash the password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        // Generate a verification token and set expiry to 24 hours
        $verificationToken = bin2hex(random_bytes(16));
        $verificationExpiry = date('Y-m-d H:i:s', strtotime('+1 day'));

        // Check if the email is already registered
        $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            $errors[] = "This email is already registered.";
        }

        if (empty($errors)) {
            // Insert the new user record including role_id = 3
            $stmt = $pdo->prepare("INSERT INTO Users (email, password_hash, role_id, isverify, verification_token, verification_expiry) VALUES (?, ?, ?, 'no', ?, ?)");
            if (!$stmt->execute([$email, $passwordHash, $role_id, $verificationToken, $verificationExpiry])) {
                $errors[] = "Insert failed.";
            }

            // Create the verification link
            $verificationLink = "https://localhost/barangayhub/functions/register.php?token=" . $verificationToken;


            // Send verification email using PHPMailer
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'barangayhub2@gmail.com';
                $mail->Password   = 'eisy hpjz rdnt bwrp';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('noreply@barangayhub.com', 'Barangay Hub');
                $mail->addAddress($email);

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
    }

    if (!empty($errors)) {
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
                    text: <?php echo json_encode($message); ?>,
                    footer: '<a href="../functions/resend_verification.php?email=<?php echo urlencode($email); ?>">Resend verification email?</a>'
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
    header("Location: ../pages/register.php");
    exit();
}
?>
