<?php
header("Cross-Origin-Opener-Policy: same-origin-allow-popups");
session_start();
//index.php
// Include the PDO connection from dbconn.php
require "../config/dbconn.php";

/**
 * Audit Trail logging function.
 *
 * @param PDO    $pdo         The PDO instance.
 * @param int    $user_id     The ID of the user performing the action.
 * @param string $action      A short action identifier (e.g., "LOGIN").
 * @param string $table_name  (Optional) The name of the table related to the action.
 * @param mixed  $record_id   (Optional) The record ID affected.
 * @param string $description A description of the action.
 */
function logAuditTrail($pdo, $user_id, $action, $table_name = null, $record_id = null, $description = '') {
    $stmt = $pdo->prepare("INSERT INTO AuditTrail (admin_user_id, action, table_name, record_id, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $table_name, $record_id, $description]);
}

/**
 * Returns the dashboard URL based on the user's role.
 */
function getDashboardUrl($role_id) {
    if ($role_id == 1) {
        return "../pages/programmer_admin.php"; 
    } elseif ($role_id == 2) {
        return "../pages/super_admin.php";
    } elseif ($role_id == 3) {
        return "../pages/barangay_admin_dashboard.php";
    } elseif ($role_id == 4) {
        return "../pages/barangay_admin_dashboard.php";
    } elseif ($role_id == 5) {
        return "../pages/barangay_admin_dashboard.php";
    } else {
        return "../pages/complete_profile.php";
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

// -------------------------------------------------------------------
// Create Dummy Barangay Admin account for BMA‑Balagtas if not exists.
// -------------------------------------------------------------------
$dummyEmail = 'barangayadmin@example.com';
$stmt = $pdo->prepare("SELECT * FROM Users WHERE email = :email LIMIT 1");
$stmt->execute([':email' => $dummyEmail]);
if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    // Get the barangay_id for "BMA‑Balagtas" from the Barangay table.
    $stmtB = $pdo->prepare("SELECT barangay_id FROM Barangay WHERE barangay_name = 'BMA-Balagtas' LIMIT 1");
    $stmtB->execute();
    $barangayRecord = $stmtB->fetch(PDO::FETCH_ASSOC);
    $barangayId = $barangayRecord ? $barangayRecord['barangay_id'] : null;
    
    if ($barangayId !== null) {
        // Hash the password securely.
        $dummyPasswordHash = password_hash('barangayadmin123', PASSWORD_DEFAULT);
        $stmtInsert = $pdo->prepare("INSERT INTO Users (email, password_hash, role_id, isverify, barangay_id) VALUES (:email, :password_hash, 2, 'yes', :barangay_id)");
        $stmtInsert->execute([
            ':email'         => $dummyEmail,
            ':password_hash' => $dummyPasswordHash,
            ':barangay_id'   => $barangayId
        ]);
        // Optionally log creation of the dummy account.
        $dummyId = $pdo->lastInsertId();
        logAuditTrail($pdo, $dummyId, "ACCOUNT CREATED", "Users", $dummyId, "Dummy Barangay Admin account created for BMA-Balagtas.");
    }
}

// -----------------------
// Traditional Email/Password Login
// -----------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Dummy login for Barangay Admin (for testing purposes).
    if ($email === 'barangayadmin@example.com' && $password === 'barangayadmin123') {
        // Instead of hardcoding, retrieve the actual dummy account record.
        $stmt = $pdo->prepare("SELECT user_id, email, role_id, barangay_id FROM Users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $dummyUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($dummyUser) {
            $_SESSION['user_id']       = $dummyUser['user_id'];
            $_SESSION['email']         = $dummyUser['email'];
            $_SESSION['role_id']       = $dummyUser['role_id'];
            $_SESSION['barangay_id']   = $dummyUser['barangay_id'];
            $_SESSION['barangay_name'] = 'BMA-Balagtas';
            
            // Log this login action using the actual user id.
            logAuditTrail($pdo, $dummyUser['user_id'], "LOGIN", "Users", $dummyUser['user_id'], "Dummy Barangay Admin login.");
            
            header("Location: " . getDashboardUrl($dummyUser['role_id']));
            exit;
        }
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
            
            $_SESSION['user_id']     = $user['user_id'];
            $_SESSION['email']       = $user['email'];
            $_SESSION['role_id']     = $user['role_id'];
            
            if (in_array($user['role_id'], [3, 4, 5])) {
                $stmt = $pdo->prepare("SELECT barangay_id, barangay_name
                                        FROM Users u
                                        JOIN Barangay b ON u.barangay_id = b.barangay_id
                                        WHERE u.user_id = :uid
                                        LIMIT 1");
                $stmt->execute([':uid' => $user['user_id']]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $_SESSION['barangay_id']   = $row['barangay_id'];
                    $_SESSION['barangay_name'] = $row['barangay_name'];
                }
            }
            
            // Log successful traditional login.
            logAuditTrail($pdo, $user['user_id'], "LOGIN", "Users", $user['user_id'], "User logged in successfully via email/password.");
            
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
                $stmt = $pdo->prepare("
                  SELECT u.barangay_id, b.barangay_name
                    FROM Users u
                    JOIN Barangay b ON u.barangay_id = b.barangay_id
                   WHERE u.user_id = :uid
                   LIMIT 1
                ");
                $stmt->execute([':uid'=>$user['user_id']]);
                if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                  $_SESSION['barangay_id']   = $row['barangay_id'];
                  $_SESSION['barangay_name'] = $row['barangay_name'];
                }
              }
            
            // Log Google OAuth login.
            logAuditTrail($pdo, $user['user_id'], "LOGIN", "Users", $user['user_id'], "User logged in via Google OAuth.");
            
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
            
            // Log new account creation via Google OAuth.
            logAuditTrail($pdo, $user_id, "ACCOUNT CREATED", "Users", $user_id, "New user account created via Google OAuth.");
            
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
