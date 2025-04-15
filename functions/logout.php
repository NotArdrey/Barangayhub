<?php
header("Cross-Origin-Opener-Policy: same-origin-allow-popups");
session_start();
require "../config/dbconn.php"; // This file creates a PDO instance as $pdo

/**
 * Audit Trail logging function.
 *
 * @param PDO    $pdo         The PDO instance.
 * @param int    $user_id     The ID of the user performing the action.
 * @param string $action      A short identifier for the action (e.g., "LOGOUT").
 * @param string $table_name  (Optional) The name of the table involved, if applicable.
 * @param mixed  $record_id   (Optional) The affected record's identifier.
 * @param string $description A longer description of the action.
 */
function logAuditTrail($pdo, $user_id, $action, $table_name = null, $record_id = null, $description = '') {
    $stmt = $pdo->prepare("INSERT INTO AuditTrail (admin_user_id, action, table_name, record_id, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $table_name, $record_id, $description]);
}

// If the user is logged in, log the logout action
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    logAuditTrail($pdo, $user_id, "LOGOUT", "Users", $user_id, "User logged out.");
}

// Destroy the session and redirect to the login page
session_destroy();
header("Location: ../pages/index.php");
exit;
?>
