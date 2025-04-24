<?php
header("Cross-Origin-Opener-Policy: same-origin-allow-popups");
session_start();
require "../config/dbconn.php";

// Existing session initialization preserved

// Preserved original audit logging function
// Preserved original audit logging function
function logAuditTrail($pdo, $user_id, $action, $table_name = null, $record_id = null, $description = '') {
    $stmt = $pdo->prepare("INSERT INTO AuditTrail (admin_user_id, action, table_name, record_id, description) VALUES (?,?,?,?,?)");
    $stmt->execute([$user_id, $action, $table_name, $record_id, $description]);
}

// Maintained original dashboard URL logic
function getDashboardUrl($role_id) {
    switch ($role_id) {
        case 1: return '../pages/programmer_admin.php';
        case 2: return '../pages/super_admin.php';
        case 3: case 4: case 5: case 6: case 7: return '../pages/barangay_admin_dashboard.php';
        case 8: default: return '../pages/user_dashboard.php';
    }
}

function postLoginRedirect($role_id, $first_name) {
    if ($role_id == 8 && empty($first_name)) {
        return '../pages/complete_profile.php';
    }
    return getDashboardUrl($role_id);
}
// Traditional Login - Added first_name to SELECT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['password'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    try {
        $stmt = $pdo->prepare("
            SELECT user_id, email, password_hash, isverify, is_active, role_id, first_name 
            FROM Users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['isverify'] !== 'yes' || $user['is_active'] !== 'yes') {
                throw new Exception("Account not verified or inactive");
            }

            // Set all original session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['first_name'] = $user['first_name']; // Preserved from original

            // Original barangay admin handling
            if (in_array($user['role_id'], [3,4,5,6,7])) {
                $stmt2 = $pdo->prepare("
                    SELECT u.barangay_id, b.barangay_name
                    FROM Users u
                    JOIN Barangay b ON u.barangay_id = b.barangay_id
                    WHERE u.user_id = ?
                ");
                $stmt2->execute([$user['user_id']]);
                if ($row = $stmt2->fetch()) {
                    $_SESSION['barangay_id'] = $row['barangay_id'];
                    $_SESSION['barangay_name'] = $row['barangay_name'];
                }
            }

            logAuditTrail($pdo, $user['user_id'], "LOGIN", "Users", $user['user_id'], "Email login");

            header("Location: " . postLoginRedirect($user['role_id'], $_SESSION['first_name']));
            exit;
        }
        throw new Exception("Invalid credentials");
    } catch (Exception $e) {
        $_SESSION['login_error'] = $e->getMessage();
        header("Location: ../pages/index.php");
        exit;
    }
}

// Google OAuth - Preserved original flow with first_name handling
if (strpos($_SERVER["CONTENT_TYPE"], "application/json") !== false) {
    $input = json_decode(file_get_contents("php://input"), true);
    
    try {
        if (empty($input['token'])) throw new Exception("No token provided");
        
        // Original token verification
        $tokenInfo = json_decode(file_get_contents(
            "https://oauth2.googleapis.com/tokeninfo?id_token=" . urlencode($input['token'])
        ), true);

        if (!$tokenInfo || $tokenInfo['aud'] !== '1070456838675-ol86nondnkulmh8s9c5ceapm42tsampq.apps.googleusercontent.com') {
            throw new Exception("Invalid token");
        }

        // Original user lookup
        $stmt = $pdo->prepare("
            SELECT user_id, email, role_id, first_name 
            FROM Users 
            WHERE email = ?
        ");
        $stmt->execute([$tokenInfo['email']]);
        $user = $stmt->fetch();

        if (!$user) {
            // Preserved original insert with added first_name initialization
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO Users (email, password_hash, isverify, role_id) 
                VALUES (?, '', 'yes', 8)
            ");
            $stmt->execute([$tokenInfo['email']]);
            $newId = $pdo->lastInsertId();
            
            // Set all original session variables
            $_SESSION['user_id'] = $newId;
            $_SESSION['email'] = $tokenInfo['email'];
            $_SESSION['role_id'] = 8;
            $_SESSION['first_name'] = null; // Explicit null for new users

            logAuditTrail($pdo, $newId, "ACCOUNT_CREATED", "Users", $newId, "Google signup");
            $pdo->commit();
        } else {
            // Existing user session setup
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role_id'] = 8; // Force resident role
            $_SESSION['first_name'] = $user['first_name']; // Preserve existing value

            // Original role update
            if ($user['role_id'] !== 8) {
                $stmt = $pdo->prepare("UPDATE Users SET role_id = 8 WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
            }
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'redirect' => postLoginRedirect(8, $_SESSION['first_name'])
        ]);
        exit;

    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Preserved fallback
http_response_code(400);
echo json_encode(['error' => 'Invalid request']);