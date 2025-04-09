<?php
header("Cross-Origin-Opener-Policy: same-origin-allow-popups");
session_start();
require "../config/dbconn.php";

// Only allow POST requests; redirect otherwise
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../pages/index.php");
    exit;
}

// Function to get dashboard URL based on role_id
function getDashboardUrl($role_id) {
    if ($role_id == 1) {
        return "../pages/super_admin_dashboard.php";
    } elseif ($role_id == 2) {
        return "../pages/barangay_admin_dashboard.php";
    } else {
        return "../pages/user_dashboard.php";
    }
}

// Function to load barangay info for Barangay Admin
function loadBarangayInfo($pdo, $email) {
    // Attempt to find the person's record (assuming email is unique)
    $stmt = $pdo->prepare("SELECT barangay_id FROM Person WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $personRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($personRecord) {
        // Get the barangay name from the Barangay table
        $stmt2 = $pdo->prepare("SELECT barangay_name FROM Barangay WHERE barangay_id = :barangay_id LIMIT 1");
        $stmt2->execute([':barangay_id' => $personRecord['barangay_id']]);
        $barangayRecord = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($barangayRecord) {
            return $barangayRecord['barangay_name'];
        }
    }
    return null;
}

// Determine if the request is JSON (for Google Sign-In) or a regular form submission
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

if (strpos($contentType, "application/json") !== false) {
    // -------------------------------
    // Google Sign-In (JSON request)
    // -------------------------------
    $input = json_decode(file_get_contents("php://input"), true);
    if (!isset($input['token'])) {
        header("Content-Type: application/json");
        http_response_code(400);
        echo json_encode(["error" => "No token provided"]);
        exit;
    }

    $token = $input['token'];
    $client_id = "1070456838675-ol86nondnkulmh8s9c5ceapm42tsampq.apps.googleusercontent.com"; // Your client ID

    // Verify token using Google's tokeninfo endpoint
    $tokenInfoUrl = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($token);
    $tokenInfoResponse = file_get_contents($tokenInfoUrl);
    if (!$tokenInfoResponse) {
        header("Content-Type: application/json");
        http_response_code(400);
        echo json_encode(["error" => "Token verification failed"]);
        exit;
    }

    $tokenInfo = json_decode($tokenInfoResponse, true);
    // Verify that the token's client ID matches your client ID
    if (!isset($tokenInfo['aud']) || $tokenInfo['aud'] != $client_id) {
        header("Content-Type: application/json");
        http_response_code(400);
        echo json_encode(["error" => "Invalid client ID"]);
        exit;
    }

    // Use the email from token info to log in the user
    $email = $tokenInfo['email'];

    try {
        // Use the $pdo connection from dbconn.php
        $stmt = $pdo->prepare("SELECT user_id, email, isverify, role_id FROM Users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Set session variables including role_id
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role_id'] = $user['role_id'];

            // For Barangay Admin, load their barangay location using the Person/Barangay tables
            if ($user['role_id'] == 2) {
                $barangayName = loadBarangayInfo($pdo, $user['email']);
                if ($barangayName !== null) {
                    $_SESSION['barangay_name'] = $barangayName;
                }
            }

            session_write_close();
            $redirectUrl = getDashboardUrl($user['role_id']);
            header("Content-Type: application/json");
            echo json_encode(["success" => true, "redirect" => $redirectUrl]);
        } else {
            // For a new user, provide default values:
            // Use email as username, an empty password, and assign default role as Resident (role_id=3)
            $defaultUsername = $email;
            $defaultPasswordHash = '';
            $defaultRoleId = 3;
    
            $stmt = $pdo->prepare("INSERT INTO Users (username, email, password_hash, isverify, role_id) VALUES (:username, :email, :password_hash, 'yes', :role_id)");
            $stmt->execute([
                ':username' => $defaultUsername,
                ':email' => $email,
                ':password_hash' => $defaultPasswordHash,
                ':role_id' => $defaultRoleId
            ]);
            $user_id = $pdo->lastInsertId();
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['email'] = $email;
            $_SESSION['role_id'] = $defaultRoleId;
            session_write_close();
            
            $redirectUrl = getDashboardUrl($defaultRoleId);
            header("Content-Type: application/json");
            echo json_encode(["success" => true, "redirect" => $redirectUrl]);
        }
    } catch (PDOException $e) {
        header("Content-Type: application/json");
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
    }
    exit;
} else {
    // -------------------------------
    // Standard Email/Password Login
    // -------------------------------
    // Retrieve and sanitize POST data
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // -------------------------------
    // Hardcoded Admin Barangay Account
    // -------------------------------
    // Check for hardcoded Barangay Admin credentials
    if ($email === 'adminbarangay@example.com' && $password === 'AdminBarangay@123') {
        // Set hardcoded admin barangay session variables
        $_SESSION['user_id'] = 2;  // Example ID for admin; adjust as needed
        $_SESSION['email'] = $email;
        $_SESSION['role_id'] = 2;  // Role for Barangay Admin
        $_SESSION['barangay_name'] = 'BMA-Balagtas';  // Replace with the appropriate barangay name
        session_write_close();
        header("Location: ../pages/barangay_admin_dashboard.php");
        exit;
    }
    
    try {
        // Use the $pdo connection from dbconn.php and include role_id in selection
        $stmt = $pdo->prepare("SELECT user_id, email, password_hash, username, isverify, role_id FROM Users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Validate credentials and check account verification
        if ($user && $user['isverify'] === 'yes' && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role_id'] = $user['role_id'];

            // For Barangay Admin, load barangay information from the Person and Barangay tables
            if ($user['role_id'] == 2) {
                $barangayName = loadBarangayInfo($pdo, $user['email']);
                if ($barangayName !== null) {
                    $_SESSION['barangay_name'] = $barangayName;
                }
            }

            session_write_close();
            $redirectUrl = getDashboardUrl($user['role_id']);
            header("Location: " . $redirectUrl);
            exit;
        } else {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Invalid email or password.'
                    }).then(() => { window.location.href = '../pages/index.php'; });
                  </script>";
            exit;
        }
    } catch (PDOException $e) {
        echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
        echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Database error: " . addslashes($e->getMessage()) . "'
                }).then(() => { window.location.href = '../pages/index.php'; });
              </script>";
        exit;
    }
}
?>
