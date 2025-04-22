<?php
session_start();
require "../config/dbconn.php";
require_once __DIR__ . '/../vendor/autoload.php';

// CSRF protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF validation failed.");
    }
}

// Authentication and role check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/index.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$roleStmt = $pdo->prepare("SELECT role_id FROM Users WHERE user_id = ?");
$roleStmt->execute([$user_id]);
if ($roleStmt->fetchColumn() != 2) {
    header("Location: ../pages/index.php");
    exit();
}

$allowed_roles    = [3,4,5,6,7,8];
$signature_roles  = [3,4,5,6,7];
$rolePlaceholders = implode(',', $allowed_roles);

// Fetch users for display
$stmt = $pdo->prepare(
    "SELECT u.*, r.role_name, b.barangay_name,
        CASE
            WHEN u.role_id IN (3,4,5,6,7) THEN
                CASE
                    WHEN u.start_term_date <= CURDATE() AND
                         (u.end_term_date IS NULL OR u.end_term_date >= CURDATE()) THEN 'active'
                    ELSE 'inactive'
                END
            ELSE 'N/A'
        END AS term_status
     FROM Users u
     JOIN Role r ON u.role_id = r.role_id
     JOIN Barangay b ON u.barangay_id = b.barangay_id
     WHERE u.role_id IN ($rolePlaceholders)
     ORDER BY u.role_id, u.last_name, u.first_name"
);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle AJAX GET user data
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get'])) {
    $id = (int) $_GET['get'];
    $stmt = $pdo->prepare(
        "SELECT u.*, r.role_name, b.barangay_name
         FROM Users u
         JOIN Role r ON u.role_id = r.role_id
         JOIN Barangay b ON u.barangay_id = b.barangay_id
         WHERE u.user_id = ? AND u.role_id IN ($rolePlaceholders)"
    );
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($data
        ? ['success' => true, 'user' => $data]
        : ['success' => false]
    );
    exit();
}

// Handle delete
if (isset($_GET['delete_id'])) {
    $delId = (int) $_GET['delete_id'];
    try {
        $pdo->beginTransaction();

        // 1) Validate user & captain rule
        $check = $pdo->prepare("SELECT role_id, barangay_id FROM Users WHERE user_id = ?");
        $check->execute([$delId]);
        $u = $check->fetch(PDO::FETCH_ASSOC);
        if (!$u || !in_array($u['role_id'], $allowed_roles)) {
            throw new Exception('Invalid user');
        }
        if ($u['role_id'] == 3) {
            $capStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM Users
                 WHERE role_id = 3 AND barangay_id = ?
                   AND (end_term_date IS NULL OR end_term_date >= CURDATE())"
            );
            $capStmt->execute([$u['barangay_id']]);
            if ($capStmt->fetchColumn() < 2) {
                throw new Exception('Cannot delete the only active Barangay Captain');
            }
        }

        // 2) Cleanup related records in proper order

        // Audit trail entries
        $pdo->prepare("DELETE FROM AuditTrail WHERE admin_user_id = ?")
            ->execute([$delId]);

        // DocumentRequestAttribute → DocumentRequest
        $pdo->prepare(
            "DELETE FROM DocumentRequestAttribute
             WHERE request_id IN (
               SELECT document_request_id FROM DocumentRequest WHERE user_id = ?
             )"
        )->execute([$delId]);
        $pdo->prepare("DELETE FROM DocumentRequest WHERE user_id = ?")
            ->execute([$delId]);

        // Blotter participants
        $pdo->prepare("DELETE FROM BlotterParticipant WHERE user_id = ?")
            ->execute([$delId]);

        // Addresses
        $pdo->prepare("DELETE FROM Address WHERE user_id = ?")
            ->execute([$delId]);

        // Events created by this user
        $pdo->prepare("DELETE FROM events WHERE created_by = ?")
            ->execute([$delId]);

        // MonthlyReportDetail → MonthlyReport
        $pdo->prepare(
            "DELETE FROM MonthlyReportDetail
             WHERE monthly_report_id IN (
               SELECT monthly_report_id FROM MonthlyReport WHERE prepared_by = ?
             )"
        )->execute([$delId]);
        $pdo->prepare("DELETE FROM MonthlyReport WHERE prepared_by = ?")
            ->execute([$delId]);

        // 3) Finally delete the user
        $pdo->prepare("DELETE FROM Users WHERE user_id = ?")
            ->execute([$delId]);

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action        = $_POST['action'] ?? '';
    $uid           = (int)($_POST['user_id'] ?? 0);
    $firstName     = htmlspecialchars($_POST['first_name'] ?? '');
    $lastName      = htmlspecialchars($_POST['last_name'] ?? '');
    $email         = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password      = $_POST['password'] ?? '';
    $roleId        = (int)($_POST['role_id'] ?? 0);
    $barangayId    = (int)($_POST['barangay_id'] ?? 0);
    $startTerm     = $_POST['start_term_date'] ?? null;
    $endTerm       = $_POST['end_term_date'] ?? null;

    $isOfficial    = in_array($roleId, $signature_roles);
    $error         = null;

    // Basic validity checks
    $chkRole = $pdo->prepare("SELECT COUNT(*) FROM Role WHERE role_id = ?");
    $chkRole->execute([$roleId]);
    if (!$chkRole->fetchColumn()) {
        $error = 'Invalid role selected';
    }
    $chkBar = $pdo->prepare("SELECT COUNT(*) FROM Barangay WHERE barangay_id = ?");
    $chkBar->execute([$barangayId]);
    if (!$chkBar->fetchColumn()) {
        $error = 'Invalid barangay selected';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } else {
        $dup = $pdo->prepare(
            "SELECT COUNT(*) FROM Users WHERE email = ? AND user_id != ?"
        );
        $dup->execute([$email, $uid]);
        if ($dup->fetchColumn() > 0) {
            $error = 'Email already in use';
        }
    }

    if ($action === 'create') {
        if (empty($password) || strlen($password) < 8) {
            $error = 'Password is required (min 8 chars)';
        }
    }

    // Term checks for officials
    if ($isOfficial) {
        if (empty($startTerm)) {
            $startTerm = date('Y-m-d');
        }
        if ($endTerm && $endTerm < $startTerm) {
            $error = 'End term cannot be before start term';
        }
        if ($roleId === 3 && !$error) {
            // prevent overlapping captains
            $ov = $pdo->prepare(
                "SELECT COUNT(*) FROM Users
                 WHERE role_id=3 AND barangay_id=? AND user_id!=?
                   AND start_term_date <= ?
                   AND (end_term_date >= ? OR end_term_date IS NULL)"
            );
            $ov->execute([$barangayId, $uid, $endTerm ?? '9999-12-31', $startTerm]);
            if ($ov->fetchColumn() > 0) {
                $error = 'Another Captain exists for this period';
            }
        }
    } else {
        // no terms for non-officials
        $startTerm = null;
        $endTerm   = null;
    }

    // —— NEW: Enforce max per-barangay counts for each official role ——
    if (!$error && in_array($roleId, [3,4,5,6,7])) {
        switch ($roleId) {
            case 3:
                $limit = 1; $label = 'Barangay Captain'; break;
            case 4:
                $limit = 1; $label = 'Barangay Secretary'; break;
            case 5:
                $limit = 1; $label = 'Barangay Treasurer'; break;
            case 7:
                $limit = 1; $label = 'Chief Officer'; break;
            case 6:
                $limit = 7; $label = 'Barangay Councilor'; break;
        }
        $cntStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM Users
             WHERE role_id = ? AND barangay_id = ?
               AND (end_term_date IS NULL OR end_term_date >= CURDATE())"
        );
        $cntStmt->execute([$roleId, $barangayId]);
        if ($cntStmt->fetchColumn() >= $limit) {
            $error = "Maximum number of {$label}(s) reached for this barangay";
        }
    }

    // Handle profile & signature uploads
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png'])) {
            $error = 'Invalid profile picture format';
        } else {
            $newName = uniqid('prof_').".$ext";
            move_uploaded_file(
                $_FILES['profile_pic']['tmp_name'],
                __DIR__ . "/../uploads/staff_pics/$newName"
            );
            $profilePic = $newName;
        }
    } else {
        $profilePic = $_POST['existing_profile_pic'] ?? 'default.png';
    }

    if ($isOfficial && isset($_FILES['signature_pic']) && $_FILES['signature_pic']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['signature_pic']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png'])) {
            $error = 'Invalid signature format';
        } else {
            $newName = uniqid('sign_').".$ext";
            move_uploaded_file(
                $_FILES['signature_pic']['tmp_name'],
                __DIR__ . "/../uploads/signatures/$newName"
            );
            $signaturePic  = $newName;
            $signatureDate = date('Y-m-d');
        }
    } else {
        $signaturePic  = $_POST['existing_signature'] ?? null;
        $signatureDate = null;
    }

    // If no errors, proceed to insert/update
    if (!$error) {
        try {
            $pdo->beginTransaction();
            $oldBarangayId = null;
            
            if ($action === 'create') {
                $pwdHash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $pdo->prepare(
                    "INSERT INTO Users
                     (first_name,last_name,email,password_hash,role_id,barangay_id,
                      id_image_path,signature_image_path,signature_date,start_term_date,end_term_date,isverify)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,'yes')"
                );
                $ins->execute([
                    $firstName,$lastName,$email,$pwdHash,$roleId,$barangayId,
                    $profilePic, $isOfficial ? $signaturePic : null,
                    $isOfficial ? $signatureDate : null,
                    $startTerm, $endTerm
                ]);
                $_SESSION['success'] = 'User created successfully';
            } else {
                // get old barangay for transfer notification
                $oldBarangayStmt = $pdo->prepare("SELECT barangay_id FROM Users WHERE user_id = ?");
                $oldBarangayStmt->execute([$uid]);
                $oldBarangayId = $oldBarangayStmt->fetchColumn();

                $fields = [
                    'first_name'=>$firstName,'last_name'=>$lastName,'email'=>$email,
                    'role_id'=>$roleId,'barangay_id'=>$barangayId,
                    'id_image_path'=>$profilePic,'start_term_date'=>$startTerm,
                    'end_term_date'=>$endTerm
                ];
                if (!empty($password)) {
                    $fields['password_hash'] = password_hash($password,PASSWORD_DEFAULT);
                }
                if ($isOfficial) {
                    $fields['signature_image_path'] = $signaturePic;
                    $fields['signature_date']       = $signatureDate;
                }
                $set = [];
                $params = [];
                foreach ($fields as $col=>$val) {
                    $set[]    = "$col = ?";
                    $params[] = $val;
                }
                $params[] = $uid;
                $upd = $pdo->prepare(
                    "UPDATE Users SET " . implode(', ',$set) . " WHERE user_id = ?"
                );
                $upd->execute($params);
                $_SESSION['success'] = 'User updated successfully';
            }

            $pdo->commit();

            // Send barangay transfer notification if moved
            if ($action === 'edit' && $oldBarangayId != $barangayId) {
                $emailStmt = $pdo->prepare("SELECT email FROM Users WHERE user_id = ?");
                $emailStmt->execute([$uid]);
                $userEmail = $emailStmt->fetchColumn();

                $barangayStmt = $pdo->prepare("SELECT barangay_name FROM Barangay WHERE barangay_id = ?");
                $barangayStmt->execute([$barangayId]);
                $newBarangay = $barangayStmt->fetchColumn();

                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.example.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'barangayhub2@gmail.com';
                    $mail->Password   = 'eisy hpjz rdnt bwrp';
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('noreply@barangayhub.com', 'Barangay Hub');
                    $mail->addAddress($userEmail);

                    $mail->isHTML(true);
                    $mail->Subject = 'Residency Transfer Update';
                    $mail->Body    = "Your barangay residency has been successfully transferred to <strong>$newBarangay</strong>.";
                    $mail->AltBody = "Your barangay residency has been successfully transferred to $newBarangay.";

                    $mail->send();
                } catch (Exception $e) {
                    error_log("Mailer Error: {$mail->ErrorInfo}");
                }
            }

            header('Location: super_admin.php');
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error saving user: ' . $e->getMessage();
        }
    }

    $_SESSION['error'] = $error;
    header('Location: super_admin.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body class="bg-gray-50">
    <main class="p-6 md:p-8 space-y-6">
        <?php if (!empty($_SESSION['success'])): ?>
            <script>
                Swal.fire({icon: 'success', title: 'Success!', text: '<?= addslashes($_SESSION['success']) ?>'});
            </script>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <div class="text-center md:text-left mb-4 md:mb-0">
                    <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
                    <p class="mt-1 text-sm text-gray-600">Manage barangay officials and residents</p>
                </div>
                <button onclick="openModal('create')" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2.5 rounded-lg flex items-center transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add New User
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b flex flex-col md:flex-row justify-between items-center gap-4">
                    <input type="text" id="searchInput" placeholder="Search users..." 
                           class="w-full md:w-64 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <div class="flex items-center space-x-4">
                        <span class="text-sm text-gray-600">Filter by:</span>
                        <select id="roleFilter" class="px-3 py-2 border rounded-lg">
                            <option value="">All Roles</option>
                            <?php
                            $roles = $pdo->query("SELECT role_id, role_name FROM Role WHERE role_id IN ($rolePlaceholders) ORDER BY role_id");
                            while ($role = $roles->fetch(PDO::FETCH_ASSOC)) {
                                echo "<option value='{$role['role_id']}'>{$role['role_name']}</option>";
                            }
                            ?>
                        </select>
                        <select id="statusFilter" class="px-3 py-2 border rounded-lg">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profile</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barangay</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Term Period</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="userTable">
                            <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-gray-50 transition-colors" 
                                data-id="<?= $user['user_id'] ?>" 
                                data-status="<?= $user['term_status'] ?>"
                                data-role="<?= $user['role_id'] ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <img src="../uploads/staff_pics/<?= htmlspecialchars($user['id_image_path']) ?>" 
                                         class="w-10 h-10 rounded-full object-cover border-2 border-purple-200"
                                         alt="Profile picture">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">
                                        <?= htmlspecialchars($user['role_name']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?= htmlspecialchars($user['barangay_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <?php if(in_array($user['role_id'], [3,4,5,6,7])): ?>
                                        <?= !empty($user['start_term_date']) ? date('M d, Y', strtotime($user['start_term_date'])) : 'Not Set' ?>
                                        - 
                                        <?= !empty($user['end_term_date']) ? date('M d, Y', strtotime($user['end_term_date'])) : 'Present' ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if(in_array($user['role_id'], [3,4,5,6,7])): ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-medium <?= $user['term_status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                            <?= ucfirst($user['term_status']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            N/A
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex gap-3">
                                    <button onclick="openModal('edit', <?= $user['user_id'] ?>)" 
                                            class="text-purple-600 hover:text-purple-900 flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                        </svg>
                                        Edit
                                    </button>
                                    <button onclick="deleteUser(<?= $user['user_id'] ?>)" 
                                            class="text-red-600 hover:text-red-900 flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="userModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl">
                <form id="userForm" method="POST" enctype="multipart/form-data" class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800" id="modalTitle"></h2>
                        <button type="button" onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="term-date">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Start Term Date *</label>
                            <input type="date" name="start_term_date" 
                                   class="flatpickr w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div class="term-date">
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Term Date</label>
                            <input type="date" name="end_term_date" 
                                   class="flatpickr w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                            <select name="role_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="">Select Role</option>
                                <?php
                                foreach ($allowed_roles as $rid) {
                                    $r = $pdo->prepare("SELECT role_id, role_name FROM Role WHERE role_id = ?");
                                    $r->execute([$rid]);
                                    $role = $r->fetch(PDO::FETCH_ASSOC);
                                    echo "<option value=\"{$role['role_id']}\">{$role['role_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Barangay *</label>
                            <select name="barangay_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                <option value="">Select Barangay</option>
                                <?php
                                $stmt = $pdo->query("SELECT * FROM Barangay ORDER BY barangay_name");
                                while ($barangay = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='{$barangay['barangay_id']}'>{$barangay['barangay_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                            <input type="text" name="first_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                            <input type="text" name="last_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>

                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                            <input type="email" name="email" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                        </div>

                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                            <input type="password" name="password" minlength="8"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <p class="text-xs text-gray-500 mt-1" id="passwordHelp"></p>
                        </div>

                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Profile Picture</label>
                            <input type="file" name="profile_pic" accept="image/png, image/jpeg" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <input type="hidden" name="existing_profile_pic" id="existingProfilePic">
                        </div>

                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">E-Signature</label>
                            <input type="file" name="signature_pic" accept="image/png, image/jpeg" 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <input type="hidden" name="existing_signature" id="existingSignature">
                        </div>
                    </div>

                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="flex justify-end gap-3 mt-8">
                        <button type="button" onclick="closeModal()" 
                                class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <script>
            flatpickr('.flatpickr', {dateFormat: "Y-m-d", allowInput: true});

            function filterTable() {
                const term = document.getElementById('searchInput').value.toLowerCase();
                const status = document.getElementById('statusFilter').value;
                const role = document.getElementById('roleFilter').value;
                
                document.querySelectorAll('#userTable tr').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    const rowStatus = row.dataset.status;
                    const rowRole = row.dataset.role;
                    
                    const showRow = text.includes(term) &&
                                  (!status || rowStatus === status) &&
                                  (!role || rowRole === role);
                    
                    row.style.display = showRow ? '' : 'none';
                });
            }

            document.getElementById('searchInput').addEventListener('input', filterTable);
            document.getElementById('statusFilter').addEventListener('change', filterTable);
            document.getElementById('roleFilter').addEventListener('change', filterTable);

            async function deleteUser(userId) {
                const { isConfirmed } = await Swal.fire({
                    title: 'Delete User?',
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Delete',
                });

                if (isConfirmed) {
                    try {
                        const response = await fetch(`super_admin.php?delete_id=${userId}`);
                        const data = await response.json();
                        if (data.success) {
                            document.querySelector(`tr[data-id="${userId}"]`).remove();
                            Swal.fire('Deleted!', 'User removed.', 'success');
                        } else {
                            Swal.fire('Error', data.message || 'Failed to delete', 'error');
                        }
                    } catch (error) {
                        Swal.fire('Error', 'Could not connect to server', 'error');
                    }
                }
            }

            function openModal(action, id = null) {
                const modal = document.getElementById('userModal');
                modal.classList.add('flex');
                modal.classList.remove('hidden');
                document.getElementById('modalTitle').textContent = action === 'create' 
                    ? 'Create New User' 
                    : 'Edit User';

                document.getElementById('formAction').value = action;
                document.getElementById('formUserId').value = id || '';
                const form = document.getElementById('userForm');
                form.reset();
                document.getElementById('passwordHelp').textContent = 
                    action === 'edit' ? 'Leave blank to keep current password' : 'Minimum 8 characters';

                const termDateDivs = document.querySelectorAll('.term-date');
                termDateDivs.forEach(div => {
                    div.style.display = 'none';
                    div.querySelector('input').required = false;
                });

                if (action === 'edit' && id) {
                    fetch(`super_admin.php?get=${id}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const u = data.user;
                                document.querySelector('[name="first_name"]').value = u.first_name;
                                document.querySelector('[name="last_name"]').value = u.last_name;
                                document.querySelector('[name="email"]').value = u.email;
                                document.querySelector('[name="role_id"]').value = u.role_id;
                                document.querySelector('[name="barangay_id"]').value = u.barangay_id;
                                document.querySelector('[name="start_term_date"]').value = u.start_term_date;
                                document.querySelector('[name="end_term_date"]').value = u.end_term_date || '';
                                document.getElementById('existingProfilePic').value = u.id_image_path;
                                document.getElementById('existingSignature').value = u.signature_image_path || '';
                                
                                const roleId = parseInt(u.role_id);
                                if ([3,4,5,6,7].includes(roleId)) {
                                    termDateDivs.forEach(div => {
                                        div.style.display = 'block';
                                        div.querySelector('input').required = true;
                                    });
                                }
                            }
                        });
                } else {
                    document.querySelector('[name="password"]').setAttribute('required', 'true');
                }

                document.querySelector('select[name="role_id"]').addEventListener('change', function() {
                    const roleId = parseInt(this.value);
                    const isOfficial = [3,4,5,6,7].includes(roleId);
                    termDateDivs.forEach(div => {
                        div.style.display = isOfficial ? 'block' : 'none';
                        div.querySelector('input').required = isOfficial;
                    });
                });
            }

            function closeModal() {
                document.getElementById('userModal').classList.add('hidden');
                document.getElementById('userModal').classList.remove('flex');
            }

            window.onclick = function(event) {
                const modal = document.getElementById('userModal');
                if (event.target === modal) closeModal();
            }
        </script>
    </main>
</body>
</html>