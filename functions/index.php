<?php
header("Cross-Origin-Opener-Policy: same-origin-allow-popups");
session_start();

// Include the PDO connection from dbconn.php
require "../config/dbconn.php";

/**
 * Returns the dashboard URL based on the user's role.
 */
function getDashboardUrl($role_id) {
    if ($role_id == 1) {
        return "../pages/super_admin_dashboard.php";
    } elseif ($role_id == 2) {
        return "../pages/barangay_admin_dashboard.php";
    } else {
        return "../pages/user_dashboard.php";
    }
}

/**
 * Load barangay info for a Barangay Admin user.
 */
function loadBarangayInfo($pdo, $email) {
    $stmt = $pdo->prepare("SELECT barangay_id FROM Users WHERE email = :email AND role_id = 2 LIMIT 1");
    $stmt->execute([':email' => $email]);
    $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($userRecord && !empty($userRecord['barangay_id'])) {
        $stmt2 = $pdo->prepare("SELECT barangay_name FROM Barangay WHERE barangay_id = :barangay_id LIMIT 1");
        $stmt2->execute([':barangay_id' => $userRecord['barangay_id']]);
        $barangayRecord = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($barangayRecord) {
            return $barangayRecord['barangay_name'];
        }
    }
    return null;
}

// -----------------------
// Traditional Email/Password Login
// -----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Hardcoded Barangay Admin credentials for testing purposes.
    if ($email === 'barangayadmin@example.com' && $password === 'barangayadmin123') {
        // Hardcoded user info for Barangay Admin.
        $_SESSION['user_id'] = 9999;  // Dummy user id for hardcoded admin.
        $_SESSION['email']   = $email;
        $_SESSION['role_id'] = 2;
        $_SESSION['barangay_name'] = 'BMA-Balagtas';  // Hardcoded barangay name.
        header("Location: " . getDashboardUrl(2));
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT user_id, email, password_hash, isverify, role_id, first_name FROM Users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['isverify'] !== 'yes') {
                $_SESSION['login_error'] = "Please verify your email address before logging in.";
                header("Location: ../pages/index.php");
                exit;
            }
            
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role_id'] = $user['role_id'];
            
            if ($user['role_id'] == 2) {
                $barangayName = loadBarangayInfo($pdo, $user['email']);
                if ($barangayName !== null) {
                    $_SESSION['barangay_name'] = $barangayName;
                }
            }
            
            if ($user['role_id'] == 3 && empty($user['first_name'])) {
                header("Location: ../pages/complete_profile.php");
            } else {
                header("Location: " . getDashboardUrl($user['role_id']));
            }
            exit;
        } else {
            $_SESSION['login_error'] = "Invalid email or password. Please try again.";
            header("Location: ../pages/index.php");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['login_error'] = "Database error. Please try again later.";
        header("Location: ../pages/index.php");
        exit;
    }
}

// -----------------------
// Google OAuth / Token-based Login Flow
// -----------------------
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

if (strpos($contentType, "application/json") !== false) {
    $input = json_decode(file_get_contents("php://input"), true);
    if (!isset($input['token'])) {
        header("Content-Type: application/json");
        http_response_code(400);
        echo json_encode(["error" => "No token provided"]);
        exit;
    }

    $token = $input['token'];
    $client_id = "1070456838675-ol86nondnkulmh8s9c5ceapm42tsampq.apps.googleusercontent.com";
    $tokenInfoUrl = "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($token);
    $tokenInfoResponse = file_get_contents($tokenInfoUrl);
    if (!$tokenInfoResponse) {
        header("Content-Type: application/json");
        http_response_code(400);
        echo json_encode(["error" => "Token verification failed"]);
        exit;
    }

    $tokenInfo = json_decode($tokenInfoResponse, true);
    if (!isset($tokenInfo['aud']) || $tokenInfo['aud'] != $client_id) {
        header("Content-Type: application/json");
        http_response_code(400);
        echo json_encode(["error" => "Invalid client ID"]);
        exit;
    }

    $email = $tokenInfo['email'];

    try {
        $stmt = $pdo->prepare("SELECT user_id, email, isverify, role_id, first_name FROM Users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            if ($user['role_id'] != 3) {
                $updateStmt = $pdo->prepare("UPDATE Users SET role_id = 3 WHERE user_id = :user_id");
                $updateStmt->execute([':user_id' => $user['user_id']]);
                $user['role_id'] = 3;
            }

            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role_id'] = $user['role_id'];

            if ($user['role_id'] == 2) {
                $barangayName = loadBarangayInfo($pdo, $user['email']);
                if ($barangayName !== null) {
                    $_SESSION['barangay_name'] = $barangayName;
                }
            }

            $redirectUrl = ($user['role_id'] == 3 && empty($user['first_name']))
                           ? "../pages/complete_profile.php"
                           : getDashboardUrl($user['role_id']);
            
            session_write_close();
            header("Content-Type: application/json");
            echo json_encode(["success" => true, "redirect" => $redirectUrl]);
            exit;
        } else {
            $stmt = $pdo->prepare("INSERT INTO Users (email, password_hash, isverify, role_id) VALUES (:email, :password_hash, 'yes', :role_id)");
            $stmt->execute([
                ':email'         => $email,
                ':password_hash' => '',  // No password for Google login.
                ':role_id'       => 3
            ]);
            $user_id = $pdo->lastInsertId();
            $_SESSION['user_id'] = $user_id;
            $_SESSION['email']   = $email;
            $_SESSION['role_id'] = 3;
            session_write_close();
            header("Content-Type: application/json");
            echo json_encode(["success" => true, "redirect" => "../pages/complete_profile.php"]);
            exit;
        }
    } catch (PDOException $e) {
        header("Content-Type: application/json");
        http_response_code(500);
        echo json_encode(["error" => "Database error: " . $e->getMessage()]);
        exit;
    }
}
?>
