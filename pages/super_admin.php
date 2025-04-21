<?php
require "../config/dbconn.php";
session_start();


if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/index.php");
    exit();
}

// Check if user is Super Admin
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT role_id FROM Users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_role = $stmt->fetchColumn();

if ($user_role != 2) {
    header("Location: ../pages/index.php");
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['get'])) {
    $userId = $_GET['get'];
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, r.role_name 
            FROM Users u
            JOIN Role r ON u.role_id = r.role_id 
            WHERE u.user_id = ? AND u.role_id IN (" . implode(',', $allowed_roles) . ")
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'user' => $user]);
            exit();
        } else {
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit();
        }
    } catch (PDOException $e) {
        error_log("Fetch error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit();
    }
}

if (isset($_GET['delete_id'])) {
    $userId = $_GET['delete_id'];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT role_id FROM Users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !in_array($user['role_id'], $allowed_roles)) {
            throw new Exception('User not found or not a Barangay Staff member');
        }

        $tables = [
            'AuditTrail' => 'admin_user_id',
            'MonthlyReport' => 'prepared_by',
            'BlotterParticipant' => 'user_id',
            'Address' => 'user_id',
            'DocumentRequest' => 'user_id'
        ];

        foreach ($tables as $table => $column) {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE $column = ?");
            $stmt->execute([$userId]);
        }

        $stmt = $pdo->prepare("DELETE FROM Users WHERE user_id = ?");
        $stmt->execute([$userId]);

        $pdo->commit();
        echo json_encode(['success' => true]);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Delete error: " . $e->getMessage());
        exit(json_encode(['success' => false, 'message' => $e->getMessage()]));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $userId = $_POST['user_id'] ?? null;

    $firstName = htmlspecialchars($_POST['first_name'] ?? '');
    $lastName = htmlspecialchars($_POST['last_name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $roleId = $_POST['role_id'] ?? null;
    $idImage = $_POST['existing_profile_pic'] ?? 'default.png';

    if (!in_array($roleId, $allowed_roles)) {
        $error = 'Invalid role selected';
    }

    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedType = finfo_file($fileInfo, $_FILES['profile_pic']['tmp_name']);
        
        if (in_array($detectedType, $allowed)) {
            $ext = array_search($detectedType, $allowed);
            $idImage = uniqid('staff_', true) . '.' . $ext;
            $uploadPath = "../uploads/staff_pics/" . basename($idImage);
            
            if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $uploadPath)) {
                $error = 'File upload failed';
            }
        } else {
            $error = 'Invalid file type';
        }
        finfo_close($fileInfo);
    }

    if (empty($error)) {
        try {
            if ($action === 'create') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO Users 
                    (first_name, last_name, email, password_hash, role_id, id_image_path) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $firstName,
                    $lastName,
                    $email,
                    $passwordHash,
                    $roleId,
                    $idImage
                ]);
                $_SESSION['success'] = 'Staff member created successfully';
            } elseif ($action === 'edit' && $userId) {
                $updateFields = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'role_id' => $roleId,
                ];

                if (!empty($password)) {
                    $updateFields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $updateFields['id_image_path'] = $idImage;

                $sql = "UPDATE Users SET ";
                $params = [];
                $sets = [];
                foreach ($updateFields as $field => $value) {
                    $sets[] = "$field = ?";
                    $params[] = $value;
                }
                $sql .= implode(', ', $sets) . " WHERE user_id = ?";
                $params[] = $userId;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $_SESSION['success'] = 'Staff member updated successfully';
            }
            
            header("Location: barangay_staff_admin.php");
            exit();
        } catch (PDOException $e) {
            $error = 'Error saving staff member: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Staff Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <main class="p-6 md:p-8 space-y-6">
        <?php if (!empty($_SESSION['success'])): ?>
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?= addslashes($_SESSION['success']) ?>'
                });
            </script>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-center mb-6">
                <div class="text-center md:text-left mb-4 md:mb-0">
                    <h1 class="text-2xl font-bold text-gray-900">Barangay Staff Management</h1>
                    <p class="mt-1 text-sm text-gray-600">Manage barangay staff members and their roles</p>
                </div>
                <button onclick="openModal('create')"
                    class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2.5 rounded-lg flex items-center transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Add New Staff
                </button>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b">
                    <input type="text" id="searchInput" placeholder="Search staff members..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profile</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="staffTable">
                            <?php foreach ($staff_members as $staff): ?>
                            <tr class="hover:bg-gray-50 transition-colors" data-id="<?= $staff['user_id'] ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <img src="../uploads/staff_pics/<?= htmlspecialchars($staff['id_image_path']) ?>" 
                                         class="w-10 h-10 rounded-full object-cover border-2 border-purple-200"
                                         alt="Profile picture">
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">
                                        <?= htmlspecialchars($staff['role_name']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($staff['email']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium flex gap-3">
                                    <button onclick="openModal('edit', <?= $staff['user_id'] ?>)" 
                                            class="text-purple-600 hover:text-purple-900 flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                        </svg>
                                        Edit
                                    </button>
                                    <button onclick="deleteStaff(<?= $staff['user_id'] ?>)" 
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

        <div id="staffModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl">
                <form id="staffForm" method="POST" enctype="multipart/form-data" class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold text-gray-800" id="modalTitle"></h2>
                        <button type="button" onclick="closeModal()" class="text-gray-500 hover:text-gray-700">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    
                    <input type="hidden" name="action" id="formAction" value="create">
                    <input type="hidden" name="user_id" id="formUserId" value="">
                    <input type="hidden" name="existing_profile_pic" id="existingProfilePic" value="default.png">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Role *</label>
                            <select name="role_id" required 
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value="">Select Role</option>
                                <?php
                                $stmt = $pdo->query("SELECT * FROM Role WHERE role_id IN (3,4,5)");
                                while ($role = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='{$role['role_id']}'>{$role['role_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                            <input type="text" name="first_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                            <input type="text" name="last_name" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>

                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                            <input type="email" name="email" required 
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>

                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Password <?= isset($_POST['action']) && $_POST['action'] === 'edit' ? '' : '*' ?></label>
                            <input type="password" name="password" <?= isset($_POST['action']) && $_POST['action'] === 'edit' ? '' : 'required' ?> minlength="8"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>

                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Profile Picture</label>
                            <div class="flex items-center justify-center w-full">
                                <label class="flex flex-col w-full h-32 border-4 border-dashed hover:border-gray-300 hover:bg-gray-50 transition-colors rounded-lg cursor-pointer">
                                    <div class="flex flex-col items-center justify-center pt-7">
                                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <p class="pt-1 text-sm tracking-wider text-gray-400">Upload photo (JPEG/PNG)</p>
                                    </div>
                                    <input type="file" name="profile_pic" accept="image/jpeg, image/png" class="opacity-0">
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 mt-8">
                        <button type="button" onclick="closeModal()" 
                                class="px-4 py-2 text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            document.getElementById('searchInput').addEventListener('input', function(e) {
                const term = e.target.value.toLowerCase();
                document.querySelectorAll('#staffTable tr').forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(term) ? '' : 'none';
                });
            });

            async function deleteStaff(userId) {
                const { isConfirmed } = await Swal.fire({
                    title: 'Delete Staff Member?',
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',


                    confirmButtonText: 'Delete',
                });

                if (isConfirmed) {
                    try {
                        const response = await fetch(`barangay_staff_admin.php?delete_id=${userId}`, { credentials: 'same-origin' });
                        const data = await response.json();
                        if (data.success) {
                            document.querySelector(`tr[data-id="${userId}"]`).remove();
                            Swal.fire('Deleted!', 'Staff member removed.', 'success');
                        } else {
                            Swal.fire('Error', data.message || 'Failed to delete', 'error');
                        }
                    } catch (error) {
                        Swal.fire('Error', 'Could not connect to server', 'error');
                    }
                }
            }

            function openModal(action, id = null) {
                const modal = document.getElementById('staffModal');
                modal.classList.remove('hidden');
                document.getElementById('modalTitle').textContent = action === 'create' 
                    ? 'Create New Staff Member' 
                    : 'Edit Staff Member';

                document.getElementById('formAction').value = action;
                document.getElementById('formUserId').value = id || '';
                const form = document.getElementById('staffForm');
                form.reset();

                if (action === 'edit' && id) {
                    fetch(`barangay_staff_admin.php?get=${id}`)
                        .then(response => {
                            if (!response.ok) throw new Error('Network error');
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                const u = data.user;
                                document.querySelector('[name="first_name"]').value = u.first_name;
                                document.querySelector('[name="last_name"]').value = u.last_name;
                                document.querySelector('[name="email"]').value = u.email;
                                document.querySelector('[name="role_id"]').value = u.role_id;
                                document.getElementById('existingProfilePic').value = u.id_image_path;
                                document.querySelector('[name="password"]').removeAttribute('required');
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            Swal.fire('Error', 'Could not load user data', 'error');
                        });
                } else {
                    document.querySelector('[name="password"]').setAttribute('required', 'true');
                }
            }

            function closeModal() {
                document.getElementById('staffModal').classList.add('hidden');
            }

            window.onclick = function(event) {
                const modal = document.getElementById('staffModal');
                if (event.target === modal) closeModal();
            }
        </script>
    </main>
</body>
</html>