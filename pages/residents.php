<?php
session_start();
require "../vendor/autoload.php";
require "../config/dbconn.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] < 2) {
    header("Location: ../pages/index.php");
    exit;
}

$current_admin_id = $_SESSION['user_id'];
$bid = $_SESSION['barangay_id'];
$role = $_SESSION['role_id'];
define('ROLE_RESIDENT', 3);

function logAuditTrail($pdo, $admin, $action, $table, $id, $desc) {
    $stmt = $pdo->prepare(
        "INSERT INTO AuditTrail (admin_user_id, action, table_name, record_id, description)
         VALUES (:admin, :act, :tbl, :rid, :desc)"
    );
    $stmt->execute([
        ':admin' => $admin,
        ':act'   => $action,
        ':tbl'   => $table,
        ':rid'   => $id,
        ':desc'  => $desc
    ]);
}
$query = "SELECT u.*, a.street AS home_address 
          FROM Users u
          LEFT JOIN Address a ON u.user_id = a.user_id
          WHERE u.role_id = :role";
$params = [':role' => ROLE_RESIDENT];

// For barangay admins (role 2), filter by their barangay
if ($role === 2) {
    $query .= " AND u.barangay_id = :bid";
    $params[':bid'] = $bid;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching residents: " . $e->getMessage());
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_resident_submit'])) {
  $user_id = (int)$_POST['edit_person_id'];
  $fields = [
      'first_name', 'middle_name', 'last_name', 'email', 'birth_date',
      'gender', 'contact_number', 'marital_status',
      'emergency_contact_name', 'emergency_contact_number',
      'emergency_contact_address'
  ];
  
  $params = [':user_id' => $user_id];
  $update = [];
  
  foreach ($fields as $f) {
      $update[] = "$f = :$f";
      $params[":$f"] = trim($_POST["edit_$f"] ?? '');
  }
  
  try {
      $pdo->beginTransaction();
      
      // Update Users table
      $stmt = $pdo->prepare("UPDATE Users SET " . implode(',', $update) . " WHERE user_id = :user_id");
      $stmt->execute($params);
      
      // Update/Insert Address table
      $home_address = trim($_POST['edit_home_address'] ?? '');
      
      // First try to update
      $check = $pdo->prepare("SELECT 1 FROM Address WHERE user_id = :user_id");
      $check->execute([':user_id' => $user_id]);

      if ($check->fetch()) {
          // 2a) It exists → do your UPDATE
          $upd = $pdo->prepare("
              UPDATE Address
                SET street = :street
              WHERE user_id = :user_id
          ");
          $upd->execute([
              ':street'  => $home_address,
              ':user_id' => $user_id
          ]);
      } else {
          // 2b) It doesn’t exist → INSERT
          $ins = $pdo->prepare("
              INSERT INTO Address (user_id, street)
              VALUES (:user_id, :street)
          ");
          $ins->execute([
              ':user_id' => $user_id,
              ':street'  => $home_address
          ]);
      }
      logAuditTrail($pdo, $current_admin_id, 'UPDATE', 'Users', $user_id, "Updated resident ID $user_id");
      $pdo->commit();
      $_SESSION['success_message'] = 'Resident updated successfully.';
  } catch (PDOException $e) {
      $pdo->rollBack();
      $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
  }
  header('Location: residents.php');
  exit;
}

require_once "../pages/header.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-4">
        <?php if(!empty($_SESSION['success_message'])): ?>
            <div class="mb-4 p-4 bg-green-100 text-green-800 rounded"><?= $_SESSION['success_message']; unset($_SESSION['success_message']); ?></div>
        <?php elseif(!empty($_SESSION['error_message'])): ?>
            <div class="mb-4 p-4 bg-red-100 text-red-800 rounded"><?= $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
        <?php endif; ?>

        <section class="mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold text-blue-800">Residents Management</h1>
                <input id="searchInput" type="text" placeholder="Search residents..." class="p-2 border rounded w-1/3">
            </div>
        </section>

        <div class="bg-white rounded-lg shadow border border-gray-200 overflow-x-auto">
            <table id="residentsTable" class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($residents as $r): ?>
                        <?php
                            $fullName = trim("{$r['first_name']} {$r['middle_name']} {$r['last_name']}");
                            $age = !empty($r['birth_date']) ? (new DateTime())->diff(new DateTime($r['birth_date']))->y : '';
                        ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($fullName) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-900"><?= htmlspecialchars($age) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-900">
                                <div class="flex items-center space-x-2">
                                    <button class="viewBtn text-blue-600 hover:text-blue-900" 
                                            data-res='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>'>
                                        View
                                    </button>
                                    <button class="editBtn bg-green-600 text-white px-2 py-1 rounded hover:bg-green-700" 
                                            data-res='<?= htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') ?>'>
                                        Edit
                                    </button>
                                    <?php if ($role === 1): // Super Admin only ?>
                                        <button class="deactivateBtn bg-yellow-500 text-white px-2 py-1 rounded hover:bg-yellow-600"
                                                data-id="<?= $r['user_id'] ?>">
                                            <?= $r['is_active'] === 'yes' ? 'Deactivate' : 'Activate' ?>
                                        </button>
                                        <button class="deleteBtn bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700"
                                                data-id="<?= $r['user_id'] ?>">
                                            Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if(empty($residents)): ?>
                        <tr>
                            <td colspan="3" class="px-4 py-4 text-center text-gray-500">No residents found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- View Modal -->
        <div id="viewResidentModal" class="hidden fixed inset-0 z-50 p-4 bg-black bg-opacity-50">
            <div class="relative mx-auto mt-20 max-w-2xl bg-white rounded-lg shadow">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold">Resident Details</h3>
                        <button type="button" data-close-modal="viewResidentModal" class="text-gray-500 hover:text-gray-700">✕</button>
                    </div>
                    <div class="space-y-4 text-sm text-gray-800">
                        <div class="grid grid-cols-2 gap-4">
                            <div><strong>First Name:</strong> <span id="viewFirstName">—</span></div>
                            <div><strong>Middle Name:</strong> <span id="viewMiddleName">—</span></div>
                            <div><strong>Last Name:</strong> <span id="viewLastName">—</span></div>
                            <div><strong>Email:</strong> <span id="viewEmail">—</span></div>
                            <div><strong>Birth Date:</strong> <span id="viewBirthDate">—</span></div>
                            <div><strong>Gender:</strong> <span id="viewGender">—</span></div>
                            <div><strong>Contact Number:</strong> <span id="viewContact">—</span></div>
                            <div><strong>Marital Status:</strong> <span id="viewMaritalStatus">—</span></div>
                            <div class="col-span-2"><strong>Home Address:</strong> <span id="viewHomeAddress">—</span></div>
                        </div>
                        <h4 class="text-lg font-medium pt-4 border-t">Emergency Contact</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div><strong>Name:</strong> <span id="viewEmergencyName">—</span></div>
                            <div><strong>Contact Number:</strong> <span id="viewEmergencyContact">—</span></div>
                            <div class="col-span-2"><strong>Address:</strong> <span id="viewEmergencyAddress">—</span></div>
                        </div>
                        <h4 class="text-lg font-medium pt-4 border-t">ID Verification</h4>
                        <div id="viewIdImage" class="mt-2"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <div id="editModal" class="hidden fixed inset-0 z-50 p-4 bg-black bg-opacity-50">
            <div class="relative mx-auto mt-20 max-w-2xl bg-white rounded-lg shadow">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold">Edit Resident</h3>
                        <button type="button" data-close-modal="editModal" class="text-gray-500 hover:text-gray-700">✕</button>
                    </div>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="edit_person_id" id="edit_person_id">
                        <div class="grid grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium">First Name</label><input type="text" name="edit_first_name" id="edit_first_name" required class="w-full p-2 border rounded"></div>
                            <div><label class="block text-sm font-medium">Middle Name</label><input type="text" name="edit_middle_name" id="edit_middle_name" class="w-full p-2 border rounded"></div>
                            <div><label class="block text-sm font-medium">Last Name</label><input type="text" name="edit_last_name" id="edit_last_name" required class="w-full p-2 border rounded"></div>
                            <div><label class="block text-sm font-medium">Email</label><input type="email" name="edit_email" id="edit_email" required class="w-full p-2 border rounded"></div>
                            <div><label class="block text-sm font-medium">Birth Date</label><input type="date" name="edit_birth_date" id="edit_birth_date" class="w-full p-2 border rounded"></div>
                            <div><label class="block text-sm font-medium">Gender</label>
                                <select name="edit_gender" id="edit_gender" class="w-full p-2 border rounded">
                                    <option value="">Select</option>
                                    <option>Male</option>
                                    <option>Female</option>
                                    <option>Others</option>
                                </select>
                            </div>
                            <div><label class="block text-sm font-medium">Contact Number</label><input type="text" name="edit_contact_number" id="edit_contact_number" class="w-full p-2 border rounded"></div>
                            <div><label class="block text-sm font-medium">Marital Status</label>
                                <select name="edit_marital_status" id="edit_marital_status" class="w-full p-2 border rounded">
                                    <option value="">Select</option>
                                    <option>Single</option>
                                    <option>Married</option>
                                    <option>Widowed</option>
                                    <option>Separated</option>
                                </select>
                            </div>
                            <div><label class="block text-sm font-medium">Emergency Contact Name</label><input type="text" name="edit_emergency_contact_name" id="edit_emergency_contact_name" class="w-full p-2 border rounded"></div>
                            <div><label class="block text-sm font-medium">Emergency Contact Number</label><input type="text" name="edit_emergency_contact_number" id="edit_emergency_contact_number" class="w-full p-2 border rounded"></div>
                            <div class="col-span-2"><label class="block text-sm font-medium">Emergency Contact Address</label><textarea name="edit_emergency_contact_address" id="edit_emergency_contact_address" class="w-full p-2 border rounded"></textarea></div>
                            <div class="col-span-2"><label class="block text-sm font-medium">Home Address</label><input type="text" name="edit_home_address" id="edit_home_address" class="w-full p-2 border rounded"></div>
                        </div>
                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" data-close-modal="editModal" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">Cancel</button>
                            <button type="submit" name="edit_resident_submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal handling
            function toggleModal(modalId) {
                document.getElementById(modalId).classList.toggle('hidden');
            }

            // Close modals
            document.querySelectorAll('[data-close-modal]').forEach(btn => {
                btn.addEventListener('click', () => {
                    document.getElementById(btn.dataset.closeModal).classList.add('hidden');
                });
            });

            // View resident
            document.querySelectorAll('.viewBtn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const resident = JSON.parse(btn.dataset.res);
                    document.getElementById('viewFirstName').textContent = resident.first_name || '—';
                    document.getElementById('viewMiddleName').textContent = resident.middle_name || '—';
                    document.getElementById('viewLastName').textContent = resident.last_name || '—';
                    document.getElementById('viewEmail').textContent = resident.email || '—';
                    document.getElementById('viewBirthDate').textContent = resident.birth_date || '—';
                    document.getElementById('viewGender').textContent = resident.gender || '—';
                    document.getElementById('viewContact').textContent = resident.contact_number || '—';
                    document.getElementById('viewMaritalStatus').textContent = resident.marital_status || '—';
                    document.getElementById('viewHomeAddress').textContent = resident.home_address || '—';
                    document.getElementById('viewEmergencyName').textContent = resident.emergency_contact_name || '—';
                    document.getElementById('viewEmergencyContact').textContent = resident.emergency_contact_number || '—';
                    document.getElementById('viewEmergencyAddress').textContent = resident.emergency_contact_address || '—';
                    document.getElementById('viewIdImage').innerHTML = resident.id_image_path ? 
                        `<img src="${resident.id_image_path}" class="max-w-[300px] h-auto border rounded" alt="ID">` : 
                        'No ID image available';
                    toggleModal('viewResidentModal');
                });
            });

            // Edit resident
            document.querySelectorAll('.editBtn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const resident = JSON.parse(btn.dataset.res);
                    document.getElementById('edit_person_id').value = resident.user_id;
                    document.getElementById('edit_first_name').value = resident.first_name || '';
                    document.getElementById('edit_middle_name').value = resident.middle_name || '';
                    document.getElementById('edit_last_name').value = resident.last_name || '';
                    document.getElementById('edit_email').value = resident.email || '';
                    document.getElementById('edit_birth_date').value = resident.birth_date || '';
                    document.getElementById('edit_gender').value = resident.gender || '';
                    document.getElementById('edit_contact_number').value = resident.contact_number || '';
                    document.getElementById('edit_marital_status').value = resident.marital_status || '';
                    document.getElementById('edit_emergency_contact_name').value = resident.emergency_contact_name || '';
                    document.getElementById('edit_emergency_contact_number').value = resident.emergency_contact_number || '';
                    document.getElementById('edit_emergency_contact_address').value = resident.emergency_contact_address || '';
                    document.getElementById('edit_home_address').value = resident.home_address || '';
                    toggleModal('editModal');
                });
            });

            // Search functionality
            document.getElementById('searchInput').addEventListener('input', function() {
                const term = this.value.toLowerCase();
                document.querySelectorAll('#residentsTable tbody tr').forEach(row => {
                    row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
                });
            });
            document.querySelectorAll('.deactivateBtn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const userId = btn.dataset.id;
                    const action  = btn.textContent.trim().toLowerCase(); // 'deactivate' or 'activate'
                    Swal.fire({
                    title: `${action.charAt(0).toUpperCase() + action.slice(1)} Resident?`,
                    text: `This will ${action} resident ID ${userId}.`,
                    icon: 'warning',
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    showCancelButton: true,
                    confirmButtonText: action.charAt(0).toUpperCase() + action.slice(1),
                    cancelButtonText: 'Cancel'
                    }).then(result => {
                    if (!result.isConfirmed) return;
                    fetch(`resident_status.php?id=${userId}&action=${action}`, {
                        method: 'PATCH'
                    })
                    .then(res => {
                        if (!res.ok) throw new Error('Request failed');
                        return res.json();
                    })
                    .then(data => {
                        if (data.success) {
                        Swal.fire('Done!', data.message, 'success').then(() => location.reload());
                        }
                    })
                    .catch(err => Swal.fire('Error', err.message, 'error'));
                    });
                });
                });
            // Delete handling
            document.querySelectorAll('.deleteBtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const userId = this.dataset.id;
                    Swal.fire({
                        title: 'Delete Resident?',
                        text: `Confirm deletion of resident ID ${userId}`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        cancelButtonColor: '#3085d6',


                        confirmButtonText: 'Delete',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch(`delete_resident.php?id=${userId}`, { method: 'DELETE' })
                                .then(response => {
                                    if (!response.ok) throw new Error('Deletion failed');
                                    return response.json();
                                })
                                .then(data => {
                                    if (data.success) {
                                        this.closest('tr').remove();
                                        Swal.fire('Deleted!', data.message, 'success');
                                    }
                                })
                                .catch(error => {
                                    Swal.fire('Error', error.message, 'error');
                                });
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>