<?php
session_start();
require "../config/dbconn.php";

// Ensure that only a Barangay Admin (role_id=2) can access this page.
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../pages/index.php");
    exit;
}

$barangayName = isset($_SESSION['barangay_name']) ? $_SESSION['barangay_name'] : "Undefined Barangay";

// Retrieve the corresponding barangay_id from the Barangay table.
$stmt = $pdo->prepare("SELECT barangay_id FROM Barangay WHERE barangay_name = :barangay_name LIMIT 1");
$stmt->execute([':barangay_name' => $barangayName]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$barangay_id = $result ? $result['barangay_id'] : null;

// ----------------------
// Dashboard Queries
// ----------------------
$totalResidents = 0;
if ($barangay_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM Person WHERE barangay_id = :barangay_id");
    $stmt->execute([':barangay_id' => $barangay_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalResidents = $res ? $res['total'] : 0;
}

$totalHouseholds = 0;
if ($barangay_id) {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT block_lot) as households FROM Address WHERE barangay_id = :barangay_id");
    $stmt->execute([':barangay_id' => $barangay_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalHouseholds = $res ? $res['households'] : 0;
}

$pendingRequests = 0;
if ($barangay_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM DocumentRequest 
                           WHERE LOWER(status) = 'pending'
                           AND person_id IN (SELECT person_id FROM Person WHERE barangay_id = :barangay_id)");
    $stmt->execute([':barangay_id' => $barangay_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    $pendingRequests = $res ? $res['pending'] : 0;
}

$recentActivities = 0;
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM AuditTrail WHERE admin_user_id = :admin_user_id");
$stmt->execute([':admin_user_id' => $_SESSION['user_id']]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);
$recentActivities = $res ? $res['total'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Barangay Administration System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Load Tesseract.js for OCR -->
  <script src="https://unpkg.com/tesseract.js@v2.1.5/dist/tesseract.min.js"></script>
  <style>
    .nav-link.active {
      background-color: #f3f4f6;
      color: #1e40af;
    }
    .nav-link.active .icon-container {
      background-color: #1e40af;
    }
    .icon-container {
      transition: transform 0.3s ease, background-color 0.3s ease;
    }
    .nav-link:hover .icon-container {
      transform: scale(1.05);
    }
    .modal {
      position: fixed;
      top: 0; left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.6);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 50;
    }
    .modal-content {
      background: #fff;
      padding: 1.5rem;
      border-radius: 0.5rem;
      width: 90%;
      max-width: 800px;
    }
  </style>
</head>
<body class="bg-gray-50">
  <!-- Sidebar -->
  <aside class="fixed left-0 top-0 h-screen w-64 bg-white border-r border-gray-200 p-4 shadow-md">
    <div class="mb-8 px-2">
      <h2 class="text-2xl font-bold text-blue-800"><?php echo htmlspecialchars($barangayName); ?></h2>
      <p class="text-sm text-gray-600">Administration System</p>
    </div>
    <nav aria-label="Main Navigation">
      <ul class="space-y-1">
        <!-- Dashboard Navigation -->
        <li>
          <a href="#dashboard" class="nav-link flex items-center p-3 rounded-lg hover:bg-gray-100 transition-colors group">
            <span class="icon-container p-2 rounded-lg bg-gray-100 mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor"
                   stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6" />
              </svg>
            </span>
            <span class="text-gray-700 font-medium">Dashboard</span>
          </a>
        </li>
        <!-- Residents Navigation -->
        <li>
          <a href="#residents" class="nav-link flex items-center p-3 rounded-lg hover:bg-gray-100 transition-colors group">
            <span class="icon-container p-2 rounded-lg bg-gray-100 mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor"
                   stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3 3 0 0 1 6.75 0Z" />
              </svg>
            </span>
            <span class="text-gray-700 font-medium">Residents</span>
          </a>
        </li>
        <!-- Blotter Navigation -->
        <li>
          <a href="#blotter" class="nav-link flex items-center p-3 rounded-lg hover:bg-gray-100 transition-colors group">
            <span class="icon-container p-2 rounded-lg bg-gray-100 mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor"
                   stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V6A9 9 0 0 0 10.5 2.25Z" />
              </svg>
            </span>
            <span class="text-gray-700 font-medium">Blotter</span>
          </a>
        </li>
        <!-- Document Requests Navigation -->
        <li>
          <a href="#docRequests" class="nav-link flex items-center p-3 rounded-lg hover:bg-gray-100 transition-colors group">
            <span class="icon-container p-2 rounded-lg bg-gray-100 mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor"
                   stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 12h6m-3-3v6M12 3v4m6 2h4M3 12H7m0 0v6m0-6v-6" />
              </svg>
            </span>
            <span class="text-gray-700 font-medium">Document Requests</span>
          </a>
          <div class="ml-10 mt-1">
            <a href="#docRequestHistory" class="nav-link flex items-center p-2 rounded-lg hover:bg-gray-100 transition-colors group">
              <span class="icon-container p-2 rounded-lg bg-gray-100 mr-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor"
                     stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </span>
              <span class="text-gray-700 font-medium">History</span>
            </a>
          </div>
        </li>
        <!-- Accounts Navigation -->
        <li>
          <a href="#accounts" class="nav-link flex items-center p-3 rounded-lg hover:bg-gray-100 transition-colors group">
            <span class="icon-container p-2 rounded-lg bg-gray-100 mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor"
                   stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3 3 0 0 1 6.75 0Z" />
              </svg>
            </span>
            <span class="text-gray-700 font-medium">Accounts</span>
          </a>
        </li>
        <!-- Settings Navigation -->
        <li>
          <a href="#settings" class="nav-link flex items-center p-3 rounded-lg hover:bg-gray-100 transition-colors group">
            <span class="icon-container p-2 rounded-lg bg-gray-100 mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor"
                   stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M11.5 3a1.5 1.5 0 011.5 1.5v.09a8.96 8.96 0 012.27.83l.07.05a1.5 1.5 0 012.12 2.12l-.05.07a8.96 8.96 0 01.83 2.27h.09a1.5 1.5 0 011.5 1.5v1a1.5 1.5 0 01-1.5 1.5h-.09a8.96 8.96 0 01-.83 2.27l.05.07a1.5 1.5 0 01-2.12 2.12l-.07-.05a8.96 8.96 0 01-2.27.83v.09a1.5 1.5 0 01-1.5 1.5h-1a1.5 1.5 0 01-1.5-1.5v-.09a8.96 8.96 0 01-2.27-.83l-.07.05a1.5 1.5 0 01-2.12-2.12l.05-.07A8.96 8.96 0 017.5 4.59V4.5A1.5 1.5 0 019 3h1z" />
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </span>
            <span class="text-gray-700 font-medium">Settings</span>
          </a>
        </li>
        <!-- Logout -->
        <li>
          <form id="logoutForm" action="../functions/logout.php" method="post">
            <button type="button" id="logoutBtn" class="nav-link flex items-center p-3 rounded-lg group">
              <span class="icon-container p-2 rounded-lg bg-gray-100 mr-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" stroke="currentColor"
                     stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 16l4-4m0 0l-4-4m4 4H7" />
                </svg>
              </span>
              <span class="text-gray-700 font-medium">Logout</span>
            </button>
          </form>
        </li>
      </ul>
    </nav>
  </aside>

  <!-- Main Content Area -->
  <main class="ml-64 p-8 space-y-8">
    <!-- Dashboard Section -->
    <section id="dashboard">
      <header class="mb-6">
        <h1 class="text-3xl font-bold text-blue-800">Dashboard</h1>
      </header>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow">
          <div class="text-3xl font-bold text-blue-800"><?php echo $totalResidents; ?></div>
          <div class="mt-2 text-gray-600">Total Residents</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow">
          <div class="text-3xl font-bold text-blue-800"><?php echo $totalHouseholds; ?></div>
          <div class="mt-2 text-gray-600">Households</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow">
          <div class="text-3xl font-bold text-blue-800"><?php echo $pendingRequests; ?></div>
          <div class="mt-2 text-gray-600">Pending Requests</div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow">
          <div class="text-3xl font-bold text-blue-800"><?php echo $recentActivities; ?></div>
          <div class="mt-2 text-gray-600">Recent Activities</div>
        </div>
      </div>
    </section>

    <!-- Residents Management Section -->
    <section id="residents">
      <header class="mb-6 flex justify-between items-center">
        <h1 class="text-3xl font-bold text-blue-800">Residents Management</h1>
        <button id="openAddResidentModal" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
          Add New Resident
        </button>
      </header>
      <div class="bg-white p-6 rounded-lg shadow">
        <div class="mb-4">
          <input type="text" id="residentSearch" placeholder="Search residents..." class="w-full p-3 border rounded-lg">
        </div>
        <div class="overflow-x-auto">
          <!-- Modified query: join Users (for account data) with Person and Address -->
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 border-b text-left text-gray-700">Name</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Age</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Address</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Status</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $stmt = $pdo->prepare("SELECT 
                  u.user_id,
                  p.person_id,
                  u.first_name,
                  u.middle_name,
                  u.last_name,
                  u.birth_date,
                  u.gender,
                  u.email,
                  u.contact_number,
                  u.marital_status,
                  u.senior_or_pwd,
                  u.solo_parent,
                  u.emergency_contact_name,
                  u.emergency_contact_number,
                  u.emergency_contact_address,
                  a.residency_type,
                  a.years_in_san_rafael,
                  a.block_lot,
                  a.phase,
                  a.street,
                  a.subdivision,
                  u.id_image_path
               FROM Users u
               JOIN Person p ON u.email = p.email
               JOIN Address a ON p.person_id = a.person_id
               WHERE u.barangay_id = :barangay_id AND u.role_id = 3");
              $stmt->execute([':barangay_id' => $barangay_id]);
              $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
              foreach ($residents as $resident):
                  $fullName = $resident['first_name'] . " " . ($resident['middle_name'] ? $resident['middle_name'] . " " : "") . $resident['last_name'];
                  $age = '';
                  if ($resident['birth_date']) {
                      $birthDate = new DateTime($resident['birth_date']);
                      $today = new DateTime();
                      $age = $today->diff($birthDate)->y;
                  }
              ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($fullName); ?></td>
                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($age); ?></td>
                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($resident['block_lot']); ?></td>
                <td class="px-4 py-2 border-b">
                  <span class="px-3 py-1 bg-green-100 text-green-800 rounded">Active</span>
                </td>
                <td class="px-4 py-2 border-b space-x-2">
                  <button class="viewResidentBtn px-3 py-1 text-blue-600 hover:text-blue-800" data-resident='<?php echo json_encode($resident); ?>'>View</button>
                  <button class="editResidentBtn px-3 py-1 text-yellow-600 hover:text-yellow-800" data-resident='<?php echo json_encode($resident); ?>'>Edit</button>
                  <button class="deleteResidentBtn px-3 py-1 text-red-600 hover:text-red-800" data-id="<?php echo $resident['person_id']; ?>">Delete</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Document Requests Section -->
    <section id="docRequests" class="hidden">
      <header class="mb-6">
        <h1 class="text-3xl font-bold text-blue-800">Document Requests</h1>
      </header>
      <div class="bg-white p-6 rounded-lg shadow">
        <button id="openAddDocRequestModal" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
          Add New Request
        </button>
        <div class="overflow-x-auto mt-4">
          <table class="min-w-full border border-gray-200" id="docRequestsTable">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 border-b text-left text-gray-700">Requested By</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Subject</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Document Type</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Request Date</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Status</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $stmt = $pdo->prepare("SELECT dr.document_request_id, dr.request_date, dr.status, dt.document_name, 
                                           CONCAT(u.first_name, ' ', u.last_name) AS requester_name,
                                           CONCAT(p.first_name, ' ', p.last_name) AS subject_name
                                      FROM DocumentRequest dr
                                      JOIN DocumentType dt ON dr.document_type_id = dt.document_type_id
                                      JOIN Users u ON dr.user_id = u.user_id
                                      JOIN Person p ON dr.person_id = p.person_id
                                      WHERE p.barangay_id = :barangay_id AND LOWER(dr.status) = 'pending'
                                      ORDER BY dr.request_date DESC");
              $stmt->execute([':barangay_id' => $barangay_id]);
              $docRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
              foreach ($docRequests as $req):
              ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($req['requester_name']); ?></td>
                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($req['subject_name']); ?></td>
                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($req['document_name']); ?></td>
                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($req['request_date']); ?></td>
                <td class="px-4 py-2 border-b">
                  <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded">
                    <?php echo htmlspecialchars($req['status']); ?>
                  </span>
                </td>
                <td class="px-4 py-2 border-b space-x-2">
                  <button class="completeDocRequestBtn px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700" data-id="<?php echo $req['document_request_id']; ?>">Complete</button>
                  <button class="deleteDocRequestBtn px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700" data-id="<?php echo $req['document_request_id']; ?>">Delete</button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($docRequests)): ?>
                <tr>
                  <td colspan="6" class="px-4 py-2 text-center text-gray-600">No pending document requests found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
    
    <!-- Document Request History Section -->
    <section id="docRequestHistory" class="hidden">
      <header class="mb-6">
        <h1 class="text-3xl font-bold text-blue-800">Document Request History</h1>
        <input type="text" id="historySearch" placeholder="Search History..." class="w-full p-3 border rounded-lg mt-4">
      </header>
      <div class="bg-white p-6 rounded-lg shadow">
        <div class="overflow-x-auto mt-4">
          <table class="min-w-full border border-gray-200" id="docRequestHistoryTable">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 border-b text-left text-gray-700">Requested By</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Subject</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Document Type</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Request Date</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $stmt = $pdo->prepare("SELECT dr.document_request_id, dr.request_date, dr.status, dt.document_name,
                                           CONCAT(u.first_name, ' ', u.last_name) AS requester_name,
                                           CONCAT(p.first_name, ' ', p.last_name) AS subject_name
                                      FROM DocumentRequest dr
                                      JOIN DocumentType dt ON dr.document_type_id = dt.document_type_id
                                      JOIN Users u ON dr.user_id = u.user_id
                                      JOIN Person p ON dr.person_id = p.person_id
                                      WHERE p.barangay_id = :barangay_id AND LOWER(dr.status) = 'complete'
                                      ORDER BY dr.request_date DESC");
              $stmt->execute([':barangay_id' => $barangay_id]);
              $completedRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
              foreach ($completedRequests as $req):
              ?>
              <tr class="history-row hover:bg-gray-50">
                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($req['requester_name']); ?></td>
                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($req['subject_name']); ?></td>
                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($req['document_name']); ?></td>
                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($req['request_date']); ?></td>
                <td class="px-4 py-2 border-b">
                  <span class="px-3 py-1 bg-green-100 text-green-800 rounded"><?php echo htmlspecialchars($req['status']); ?></span>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if(empty($completedRequests)): ?>
                <tr>
                  <td colspan="5" class="px-4 py-2 text-center text-gray-600">No completed document requests found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Accounts Section -->
    <section id="accounts" class="hidden">
      <header class="mb-6">
        <h1 class="text-3xl font-bold text-blue-800">User Accounts</h1>
      </header>
      <div class="bg-white p-6 rounded-lg shadow">
        <div class="overflow-x-auto">
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 border-b text-left text-gray-700">Username</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Role</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Status</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $stmt = $pdo->prepare("SELECT user_id, email, role_id, isverify FROM Users WHERE role_id <> 1");
              $stmt->execute();
              $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
              foreach ($users as $user):
              ?>
              <tr class="hover:bg-gray-50">
                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($user['email']); ?></td>
                <td class="px-4 py-2 border-b"><?php echo ($user['role_id'] == 2 ? "Barangay Admin" : "Resident"); ?></td>
                <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($user['isverify']); ?></td>
                <td class="px-4 py-2 border-b space-x-2">
                  <button class="viewAccountBtn px-3 py-1 text-blue-600 hover:text-blue-800" data-account='<?php echo json_encode($user); ?>'>View</button>
                  <button class="editAccountBtn px-3 py-1 text-yellow-600 hover:text-yellow-800" data-account='<?php echo json_encode($user); ?>'>Edit</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Settings Section -->
    <section id="settings" class="hidden">
      <header class="mb-6">
        <h1 class="text-3xl font-bold text-blue-800">Settings</h1>
      </header>
      <div class="bg-white p-6 rounded-lg shadow">
        <p class="text-gray-600 mb-4">Adjust your system settings and preferences below.</p>
        <form class="space-y-4">
          <div>
            <label class="block text-gray-700">Site Title</label>
            <input type="text" placeholder="Enter site title" class="w-full p-3 border rounded-lg">
          </div>
          <div>
            <label class="block text-gray-700">Contact Email</label>
            <input type="email" placeholder="Enter contact email" class="w-full p-3 border rounded-lg">
          </div>
          <button type="submit" class="px-4 py-2 bg-blue-800 text-white rounded-lg hover:bg-blue-900 transition-colors">Save Changes</button>
        </form>
      </div>
    </section>

    <!-- Revised Blotter Section -->
    <section id="blotter" class="hidden">
      <header class="mb-6 flex justify-between items-center">
        <h1 class="text-3xl font-bold text-blue-800">Blotter Cases</h1>
        <button id="openAddBlotterModal" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
          Add New Case
        </button>
      </header>
      <div class="bg-white p-6 rounded-lg shadow">
        <div class="overflow-x-auto">
          <?php
            $stmt = $pdo->prepare("SELECT blotter_case_id, date_reported, location, description, status FROM BlotterCase WHERE barangay_id = :barangay_id ORDER BY date_reported DESC");
            $stmt->execute([':barangay_id' => $barangay_id]);
            $blotterCases = $stmt->fetchAll(PDO::FETCH_ASSOC);
          ?>
          <table class="min-w-full border border-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-2 border-b text-left text-gray-700">Case ID</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Date Reported</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Location</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Description</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Status</th>
                <th class="px-4 py-2 border-b text-left text-gray-700">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($blotterCases as $case): ?>
                <tr class="hover:bg-gray-50">
                  <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($case['blotter_case_id']); ?></td>
                  <td class="px-4 py-2 border-b"><?php echo date("Y-m-d H:i", strtotime($case['date_reported'])); ?></td>
                  <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($case['location']); ?></td>
                  <td class="px-4 py-2 border-b">
                    <?php
                      $desc = htmlspecialchars($case['description']);
                      echo (strlen($desc) > 50) ? substr($desc, 0, 50) . "..." : $desc;
                    ?>
                  </td>
                  <td class="px-4 py-2 border-b">
                    <span class="px-3 py-1 <?php echo ($case['status'] == 'Open' || $case['status'] == 'Pending') ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'; ?> rounded">
                      <?php echo htmlspecialchars($case['status']); ?>
                    </span>
                  </td>
                  <td class="px-4 py-2 border-b space-x-2">
                    <button class="viewBlotterBtn px-3 py-1 text-blue-600 hover:text-blue-800" data-case='<?php echo json_encode($case); ?>'>View</button>
                    <button class="deleteBlotterBtn px-3 py-1 text-red-600 hover:text-red-800" data-id="<?php echo $case['blotter_case_id']; ?>">Delete</button>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if(empty($blotterCases)): ?>
                <tr>
                  <td colspan="6" class="px-4 py-2 text-center text-gray-600">No blotter cases found.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
  </main>

  <!-- ===================
       MODALS SECTION
       =================== -->

  <!-- Add Resident Modal -->
  <div id="addResidentModal" class="modal">
    <div class="modal-content">
      <h2 class="text-2xl font-bold mb-4">Add New Resident</h2>
      <form method="POST" action="../functions/resident_actions.php" enctype="multipart/form-data">
        <!-- New File Input for Resident Picture (for Auto-fill) -->
        <div class="mb-4">
          <label class="block text-gray-700">Upload Resident Picture (for Auto-fill)</label>
          <input type="file" name="resident_pic" id="resident_pic" accept="image/*" class="w-full p-2 border rounded">
        </div>
        <!-- Personal Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-gray-700">First Name</label>
            <input type="text" name="first_name" id="first_name" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Middle Name</label>
            <input type="text" name="middle_name" id="middle_name" class="w-full p-2 border rounded">
          </div>
          <div>
            <label class="block text-gray-700">Last Name</label>
            <input type="text" name="last_name" id="last_name" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Birth Date</label>
            <input type="date" name="birth_date" id="birth_date" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Gender</label>
            <select name="gender" id="gender" class="w-full p-2 border rounded" required>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Others">Others</option>
            </select>
          </div>
          <div>
            <label class="block text-gray-700">Email</label>
            <input type="email" name="email" id="email" class="w-full p-2 border rounded" required>
          </div>
          <!-- New Password Field for Account Credentials -->
          <div>
            <label class="block text-gray-700">Password</label>
            <input type="password" name="password" id="password" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Contact Number</label>
            <input type="text" name="contact_number" id="contact_number" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Marital Status</label>
            <select name="marital_status" id="marital_status" class="w-full p-2 border rounded">
              <option value="Single">Single</option>
              <option value="Married">Married</option>
              <option value="Widowed">Widowed</option>
              <option value="Separated">Separated</option>
            </select>
          </div>
          <div>
            <label class="block text-gray-700">Senior/PWD</label>
            <select name="senior_or_pwd" id="senior_or_pwd" class="w-full p-2 border rounded">
              <option value="No">No</option>
              <option value="Yes">Yes</option>
            </select>
          </div>
          <div>
            <label class="block text-gray-700">Solo Parent</label>
            <select name="solo_parent" id="solo_parent" class="w-full p-2 border rounded">
              <option value="No">No</option>
              <option value="Yes">Yes</option>
            </select>
          </div>
        </div>
        <!-- Emergency Information -->
        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-gray-700">Emergency Contact Name</label>
            <input type="text" name="emergency_contact_name" class="w-full p-2 border rounded">
          </div>
          <div>
            <label class="block text-gray-700">Emergency Contact Number</label>
            <input type="text" name="emergency_contact_number" class="w-full p-2 border rounded">
          </div>
          <div>
            <label class="block text-gray-700">Emergency Contact Address</label>
            <input type="text" name="emergency_contact_address" class="w-full p-2 border rounded">
          </div>
        </div>
        <!-- Address Information -->
        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-gray-700">Residency Type</label>
            <select name="residency_type" class="w-full p-2 border rounded" required>
              <option value="Home Owner">Home Owner</option>
              <option value="Renter">Renter</option>
              <option value="Boarder">Boarder</option>
              <option value="Living-In">Living-In</option>
            </select>
          </div>
          <div>
            <label class="block text-gray-700">Years in San Rafael</label>
            <input type="number" name="years_in_san_rafael" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Block/Lot</label>
            <input type="text" name="block_lot" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Phase</label>
            <input type="text" name="phase" class="w-full p-2 border rounded">
          </div>
          <div>
            <label class="block text-gray-700">Street</label>
            <input type="text" name="street" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Subdivision</label>
            <input type="text" name="subdivision" class="w-full p-2 border rounded">
          </div>
        </div>
        <div class="mt-4 flex justify-end">
          <button type="button" class="closeModal px-4 py-2 bg-gray-500 text-white rounded mr-2">Cancel</button>
          <button type="submit" name="add_resident_submit" class="px-4 py-2 bg-green-600 text-white rounded">Add Resident</button>
        </div>
      </form>
    </div>
  </div>

  <!-- View Resident Modal -->
  <div id="viewResidentModal" class="modal">
    <div class="modal-content">
      <h2 class="text-2xl font-bold mb-4">Resident Details</h2>
      <div id="viewResidentContent" class="mb-4">
        <!-- Details populated via JavaScript -->
      </div>
      <div class="flex justify-end">
        <button type="button" class="closeModal px-4 py-2 bg-gray-500 text-white rounded">Close</button>
      </div>
    </div>
  </div>

  <!-- Edit Resident Modal -->
  <div id="editResidentModal" class="modal">
    <div class="modal-content">
      <h2 class="text-2xl font-bold mb-4">Edit Resident</h2>
      <form method="POST" action="../functions/resident_actions.php" enctype="multipart/form-data">
        <input type="hidden" name="edit_person_id" id="edit_person_id">
        <!-- Government ID Section -->
        <div class="mb-4">
          <label class="block text-gray-700">Current Government ID</label>
          <div id="govIdDisplay" class="mb-2 text-sm text-gray-600"></div>
          <label class="block text-gray-700">Change Government ID</label>
          <input type="file" name="edit_government_id" id="edit_government_id" accept="image/*" class="w-full p-2 border rounded">
        </div>
        <!-- Personal Information Fields for Editing -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-gray-700">First Name</label>
            <input type="text" name="edit_first_name" id="edit_first_name" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Middle Name</label>
            <input type="text" name="edit_middle_name" id="edit_middle_name" class="w-full p-2 border rounded">
          </div>
          <div>
            <label class="block text-gray-700">Last Name</label>
            <input type="text" name="edit_last_name" id="edit_last_name" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Birth Date</label>
            <input type="date" name="edit_birth_date" id="edit_birth_date" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Gender</label>
            <select name="edit_gender" id="edit_gender" class="w-full p-2 border rounded" required>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Others">Others</option>
            </select>
          </div>
          <div>
            <label class="block text-gray-700">Email</label>
            <input type="email" name="edit_email" id="edit_email" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Contact Number</label>
            <input type="text" name="edit_contact_number" id="edit_contact_number" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Marital Status</label>
            <select name="edit_marital_status" id="edit_marital_status" class="w-full p-2 border rounded">
              <option value="Single">Single</option>
              <option value="Married">Married</option>
              <option value="Widowed">Widowed</option>
              <option value="Separated">Separated</option>
            </select>
          </div>
          <div>
            <label class="block text-gray-700">Senior/PWD</label>
            <select name="edit_senior_or_pwd" id="edit_senior_or_pwd" class="w-full p-2 border rounded">
              <option value="No">No</option>
              <option value="Yes">Yes</option>
            </select>
          </div>
          <div>
            <label class="block text-gray-700">Solo Parent</label>
            <select name="edit_solo_parent" id="edit_solo_parent" class="w-full p-2 border rounded">
              <option value="No">No</option>
              <option value="Yes">Yes</option>
            </select>
          </div>
        </div>
        <!-- Emergency Information Fields -->
        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-gray-700">Emergency Contact Name</label>
            <input type="text" name="edit_emergency_contact_name" id="edit_emergency_contact_name" class="w-full p-2 border rounded">
          </div>
          <div>
            <label class="block text-gray-700">Emergency Contact Number</label>
            <input type="text" name="edit_emergency_contact_number" id="edit_emergency_contact_number" class="w-full p-2 border rounded">
          </div>
          <div>
            <label class="block text-gray-700">Emergency Contact Address</label>
            <input type="text" name="edit_emergency_contact_address" id="edit_emergency_contact_address" class="w-full p-2 border rounded">
          </div>
        </div>
        <!-- Address Information Fields -->
        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-gray-700">Residency Type</label>
            <select name="edit_residency_type" id="edit_residency_type" class="w-full p-2 border rounded" required>
              <option value="Home Owner">Home Owner</option>
              <option value="Renter">Renter</option>
              <option value="Boarder">Boarder</option>
              <option value="Living-In">Living-In</option>
            </select>
          </div>
          <div>
            <label class="block text-gray-700">Years in San Rafael</label>
            <input type="number" name="edit_years_in_san_rafael" id="edit_years_in_san_rafael" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Block/Lot</label>
            <input type="text" name="edit_block_lot" id="edit_block_lot" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Phase</label>
            <input type="text" name="edit_phase" id="edit_phase" class="w-full p-2 border rounded">
          </div>
          <div>
            <label class="block text-gray-700">Street</label>
            <input type="text" name="edit_street" id="edit_street" class="w-full p-2 border rounded" required>
          </div>
          <div>
            <label class="block text-gray-700">Subdivision</label>
            <input type="text" name="edit_subdivision" id="edit_subdivision" class="w-full p-2 border rounded">
          </div>
        </div>
        <div class="mt-4 flex justify-end">
          <button type="button" class="closeModal px-4 py-2 bg-gray-500 text-white rounded mr-2">Cancel</button>
          <button type="submit" name="edit_resident_submit" class="px-4 py-2 bg-yellow-600 text-white rounded">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Account Modal -->
  <div id="editAccountModal" class="modal">
    <div class="modal-content">
      <h2 class="text-2xl font-bold mb-4">Edit Account</h2>
      <form method="POST" action="../functions/resident_actions.php">
        <input type="hidden" name="user_id" id="account_user_id">
        <div class="mb-4">
          <label class="block text-gray-700">Email</label>
          <input type="email" name="edit_email" id="edit_email" class="w-full p-2 border rounded" required>
        </div>
        <div class="mb-4">
          <label class="block text-gray-700">Role</label>
          <select name="edit_role" id="edit_role" class="w-full p-2 border rounded" required>
            <option value="2">Barangay Admin</option>
            <option value="3">Resident</option>
          </select>
        </div>
        <div class="mb-4">
          <label class="block text-gray-700">New Password (leave blank to keep unchanged)</label>
          <input type="password" name="edit_password" id="edit_password" class="w-full p-2 border rounded">
        </div>
        <div class="flex justify-end">
          <button type="button" class="closeModal px-4 py-2 bg-gray-500 text-white rounded mr-2">Cancel</button>
          <button type="submit" name="update_account_submit" class="px-4 py-2 bg-yellow-600 text-white rounded">Update Account</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Add Document Request Modal -->
  <div id="addDocRequestModal" class="modal">
    <div class="modal-content">
      <h2 class="text-2xl font-bold mb-4">Add New Document Request</h2>
      <form method="POST" action="../functions/document_request.php" id="addDocRequestForm">
        <div class="mb-4">
          <label class="block text-gray-700">Document Type</label>
          <select name="document_type" id="documentType" class="w-full p-2 border rounded" required>
            <option value="">Select Document</option>
            <?php
            $stmt = $pdo->prepare("SELECT document_type_id, document_name FROM DocumentType");
            $stmt->execute();
            $documentTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($documentTypes as $docType) {
                $dataType = strtolower(str_replace(' ', '', $docType['document_name']));
                echo "<option value='" . $docType['document_type_id'] . "' data-type='" . $dataType . "'>" . htmlspecialchars($docType['document_name']) . "</option>";
            }
            ?>
          </select>
        </div>
        <!-- Optional: New Person Details -->
        <div class="mb-4">
          <p class="text-gray-700 font-semibold">New Person Details (if not already registered)</p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-gray-700">First Name</label>
              <input type="text" name="person_first_name" class="w-full p-2 border rounded">
            </div>
            <div>
              <label class="block text-gray-700">Middle Name</label>
              <input type="text" name="person_middle_name" class="w-full p-2 border rounded">
            </div>
            <div>
              <label class="block text-gray-700">Last Name</label>
              <input type="text" name="person_last_name" class="w-full p-2 border rounded">
            </div>
            <div>
              <label class="block text-gray-700">Birth Date</label>
              <input type="date" name="person_birth_date" class="w-full p-2 border rounded">
            </div>
            <div>
              <label class="block text-gray-700">Gender</label>
              <select name="person_gender" class="w-full p-2 border rounded">
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Others">Others</option>
              </select>
            </div>
            <div>
              <label class="block text-gray-700">Email</label>
              <input type="email" name="person_email" class="w-full p-2 border rounded">
            </div>
            <div>
              <label class="block text-gray-700">Contact Number</label>
              <input type="text" name="person_contact_number" class="w-full p-2 border rounded">
            </div>
            <div>
              <label class="block text-gray-700">Marital Status</label>
              <select name="person_marital_status" class="w-full p-2 border rounded">
                <option value="Single">Single</option>
                <option value="Married">Married</option>
                <option value="Widowed">Widowed</option>
                <option value="Separated">Separated</option>
              </select>
            </div>
            <div>
              <label class="block text-gray-700">Senior/PWD</label>
              <select name="person_senior_or_pwd" class="w-full p-2 border rounded">
                <option value="None">None</option>
                <option value="Senior Citizen">Senior Citizen</option>
                <option value="PWD">PWD</option>
              </select>
            </div>
            <div>
              <label class="block text-gray-700">Solo Parent</label>
              <select name="person_solo_parent" class="w-full p-2 border rounded">
                <option value="No">No</option>
                <option value="Yes">Yes</option>
              </select>
            </div>
            <div>
              <label class="block text-gray-700">Emergency Contact Name</label>
              <input type="text" name="person_emergency_contact_name" class="w-full p-2 border rounded">
            </div>
            <div>
              <label class="block text-gray-700">Emergency Contact Number</label>
              <input type="text" name="person_emergency_contact_number" class="w-full p-2 border rounded">
            </div>
            <div>
              <label class="block text-gray-700">Emergency Contact Address</label>
              <input type="text" name="person_emergency_contact_address" class="w-full p-2 border rounded">
            </div>
          </div>
        </div>
        <!-- Optional: Existing Person ID -->
        <div class="mb-4">
          <label class="block text-gray-700">Existing Person ID (optional)</label>
          <input type="number" name="person_id" class="w-full p-2 border rounded">
        </div>
        <!-- Conditional Document Specific Fields -->
        <div id="barangayclearanceFields" class="doc-fields" style="display:none;">
          <div class="mb-4">
            <label class="block text-gray-700">Clearance Purpose</label>
            <input type="text" name="clearance_purpose" class="w-full p-2 border rounded">
          </div>
        </div>
        <div id="proofofresidencyFields" class="doc-fields" style="display:none;">
          <div class="mb-4">
            <label class="block text-gray-700">Residency Duration</label>
            <input type="text" name="residency_duration" class="w-full p-2 border rounded">
          </div>
          <div class="mb-4">
            <label class="block text-gray-700">Residency Purpose</label>
            <input type="text" name="residency_purpose" class="w-full p-2 border rounded">
          </div>
        </div>
        <div id="goodmoralcertificateFields" class="doc-fields" style="display:none;">
          <div class="mb-4">
            <label class="block text-gray-700">GMC Purpose</label>
            <input type="text" name="gmc_purpose" class="w-full p-2 border rounded">
          </div>
        </div>
        <div id="indigencycertificateFields" class="doc-fields" style="display:none;">
          <div class="mb-4">
            <label class="block text-gray-700">NIC Reason</label>
            <input type="text" name="nic_reason" class="w-full p-2 border rounded">
          </div>
          <div class="mb-4">
            <label class="block text-gray-700">Indigency Income</label>
            <input type="number" step="0.01" name="indigency_income" class="w-full p-2 border rounded">
          </div>
          <div class="mb-4">
            <label class="block text-gray-700">Indigency Reason</label>
            <input type="text" name="indigency_reason" class="w-full p-2 border rounded">
          </div>
        </div>
        <!-- Common Field: Remarks -->
        <div class="mb-4">
          <label class="block text-gray-700">Remarks</label>
          <textarea name="remarks" class="w-full p-2 border rounded" rows="4"></textarea>
        </div>
        <div class="flex justify-end">
          <button type="button" class="closeModal px-4 py-2 bg-gray-500 text-white rounded mr-2">Cancel</button>
          <button type="submit" name="document_request_submit" class="px-4 py-2 bg-green-600 text-white rounded">Submit Request</button>
        </div>
      </form>
    </div>
  </div>

  <!-- View Document Request Modal -->
  <div id="viewDocRequestModal" class="modal">
    <div class="modal-content">
      <h2 class="text-2xl font-bold mb-4">Document Request Details</h2>
      <div id="viewDocRequestContent" class="mb-4">
        <!-- Details will be injected here by JavaScript -->
      </div>
      <div class="flex justify-end">
        <button type="button" class="closeModal px-4 py-2 bg-gray-500 text-white rounded">Close</button>
      </div>
    </div>
  </div>

  <!-- Add Blotter Case Modal -->
  <div id="addBlotterModal" class="modal">
    <div class="modal-content">
      <h2 class="text-2xl font-bold mb-4">Add New Blotter Case</h2>
      <form method="POST" action="../functions/blotter_case.php" enctype="multipart/form-data">
        <div class="mb-4">
          <label class="block text-gray-700">Case Description</label>
          <textarea name="complaint" class="w-full p-2 border rounded" rows="4" placeholder="Describe the incident in detail" required></textarea>
        </div>
        <div class="mb-4">
          <label class="block text-gray-700">Location</label>
          <input type="text" name="location" class="w-full p-2 border rounded" placeholder="Enter incident location" required>
        </div>
        <div class="mb-4">
          <label class="block text-gray-700">Upload Supporting File</label>
          <input type="file" name="complaint_file" class="w-full p-2 border rounded">
        </div>
        <div class="mb-4">
          <label class="block text-gray-700">Upload Voice Recording (Optional)</label>
          <input type="file" name="voice_recording" accept="audio/*" class="w-full p-2 border rounded">
        </div>
        <div class="mb-4">
          <button type="button" id="transcribeButton" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
            Transcribe Voice Recording
          </button>
          <textarea id="transcriptionText" name="transcription_text" placeholder="The transcription will appear here..." class="w-full p-2 border rounded mt-2" style="display:none;" rows="3"></textarea>
        </div>
        <div class="mb-4">
          <label class="block text-gray-700">Case Category</label>
          <select name="case_category" class="w-full p-2 border rounded">
            <option value="">Select Category</option>
            <?php
              $stmt = $pdo->prepare("SELECT category_id, category_name FROM CaseCategory");
              $stmt->execute();
              $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
              foreach($categories as $cat) {
                  echo "<option value='" . $cat['category_id'] . "'>" . htmlspecialchars($cat['category_name']) . "</option>";
              }
            ?>
          </select>
        </div>
        <div class="mb-4 border p-4 rounded">
          <h3 class="text-lg font-semibold mb-2">Case Intervention (Optional)</h3>
          <div class="mb-4">
            <label class="block text-gray-700">Intervention</label>
            <select name="intervention" class="w-full p-2 border rounded">
              <option value="">Select Intervention</option>
              <?php
                $stmt = $pdo->prepare("SELECT intervention_id, intervention_name FROM CaseIntervention");
                $stmt->execute();
                $interventions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach($interventions as $item) {
                    echo "<option value='" . $item['intervention_id'] . "'>" . htmlspecialchars($item['intervention_name']) . "</option>";
                }
              ?>
            </select>
          </div>
          <div class="mb-4">
            <label class="block text-gray-700">Date Intervened</label>
            <input type="datetime-local" name="intervention_date" class="w-full p-2 border rounded">
          </div>
          <div class="mb-4">
            <label class="block text-gray-700">Intervention Remarks</label>
            <textarea name="intervention_remarks" class="w-full p-2 border rounded" rows="3"></textarea>
          </div>
        </div>
        <div class="flex justify-end">
          <button type="button" class="closeModal px-4 py-2 bg-gray-500 text-white rounded mr-2">Cancel</button>
          <button type="submit" name="blotter_submit" class="px-4 py-2 bg-green-600 text-white rounded">Submit Case</button>
        </div>
      </form>
    </div>
  </div>

  <!-- JavaScript Section -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Navigation and section toggling
      const navLinks = document.querySelectorAll('.nav-link');
      const sections = document.querySelectorAll('main > section');

      function setActiveSection(targetId) {
        sections.forEach(section => {
          section.classList.add('hidden');
          if (section.id === targetId.substring(1)) {
            section.classList.remove('hidden');
          }
        });
      }

      function setActiveLink(clickedLink) {
        navLinks.forEach(link => {
          link.classList.remove('active');
          link.querySelector('.icon-container').classList.remove('bg-blue-800');
          link.querySelector('svg').classList.remove('text-white');
        });
        clickedLink.classList.add('active');
        clickedLink.querySelector('.icon-container').classList.add('bg-blue-800');
        clickedLink.querySelector('svg').classList.add('text-white');
      }

      navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          const targetId = this.getAttribute('href');
          setActiveSection(targetId);
          setActiveLink(this);
        });
      });

      // Set default active section.
      const defaultLink = document.querySelector('.nav-link[href="#dashboard"]');
      setActiveLink(defaultLink);
      setActiveSection('#dashboard');

      // Modal handling
      const modals = document.querySelectorAll('.modal');
      const closeButtons = document.querySelectorAll('.closeModal');
      closeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
          btn.closest('.modal').style.display = 'none';
        });
      });

      document.getElementById('openAddResidentModal').addEventListener('click', () => {
        document.getElementById('addResidentModal').style.display = 'flex';
      });
      document.getElementById('openAddDocRequestModal').addEventListener('click', () => {
        document.getElementById('addDocRequestModal').style.display = 'flex';
      });
      document.getElementById('openAddBlotterModal').addEventListener('click', () => {
        document.getElementById('addBlotterModal').style.display = 'flex';
      });

      // Document Request: Show/hide conditional fields based on Document Type selection
      document.getElementById('documentType').addEventListener('change', function() {
        var selectedType = this.options[this.selectedIndex].getAttribute('data-type');
        document.querySelectorAll('.doc-fields').forEach(div => div.style.display = 'none');
        if (selectedType === 'barangayclearance') {
            document.getElementById('barangayclearanceFields').style.display = 'block';
        } else if (selectedType === 'proofofresidency') {
            document.getElementById('proofofresidencyFields').style.display = 'block';
        } else if (selectedType === 'goodmoralcertificate') {
            document.getElementById('goodmoralcertificateFields').style.display = 'block';
        } else if (selectedType === 'indigencycertificate') {
            document.getElementById('indigencycertificateFields').style.display = 'block';
        }
      });

      // Simulated transcription for voice recording.
      document.getElementById('transcribeButton').addEventListener('click', function() {
        const voiceInput = document.querySelector('input[name="voice_recording"]');
        if (voiceInput && voiceInput.files.length > 0) {
          const simulatedTranscription = "Simulated transcription: The audio indicates the incident occurred at the stated location.";
          const transcriptionText = document.getElementById('transcriptionText');
          transcriptionText.style.display = 'block';
          transcriptionText.value = simulatedTranscription;
        } else {
          alert("Please select a voice recording file first.");
        }
      });

      // Logout confirmation
      document.getElementById('logoutBtn').addEventListener('click', function(e) {
        e.preventDefault();
        Swal.fire({
          title: 'Are you sure you want to logout?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, logout',
          cancelButtonText: 'Cancel',
          customClass: {
            confirmButton: 'bg-blue-600 text-white px-4 py-2 mr-2 rounded-lg hover:bg-blue-700',
            cancelButton: 'bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600'
          },
          buttonsStyling: false
        }).then((result) => {
          if (result.isConfirmed) {
            document.getElementById('logoutForm').submit();
          }
        });
      });

      const historySearch = document.getElementById('historySearch');
      historySearch.addEventListener('keyup', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.history-row').forEach(row => {
          row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
      });

      // Document Request Actions
      document.querySelectorAll('.completeDocRequestBtn').forEach(button => {
        button.addEventListener('click', function() {
          let requestId = this.getAttribute('data-id');
          Swal.fire({
            title: 'Complete Request?',
            text: 'Are you sure you want to mark this request as complete?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, complete it!',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              window.location.href = "../functions/document_request.php?action=complete_doc_request&id=" + requestId;
            }
          });
        });
      });

      document.querySelectorAll('.deleteDocRequestBtn').forEach(button => {
        button.addEventListener('click', function() {
          let requestId = this.getAttribute('data-id');
          Swal.fire({
            title: 'Delete Request?',
            text: 'Are you sure you want to delete this document request?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              window.location.href = "../functions/document_request.php?action=delete_doc_request&id=" + requestId;
            }
          });
        });
      });

      // Blotter Case Actions
      document.querySelectorAll('.deleteBlotterBtn').forEach(button => {
        button.addEventListener('click', function() {
          let blotterId = this.getAttribute('data-id');
          Swal.fire({
            title: 'Delete Blotter Case?',
            text: 'Are you sure you want to delete this blotter case?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              window.location.href = "../functions/blotter_case.php?action=delete_blotter&id=" + blotterId;
            }
          });
        });
      });

      // Resident View, Edit, and Delete Actions
      document.querySelectorAll('.viewResidentBtn').forEach(button => {
        button.addEventListener('click', function() {
          let resident = JSON.parse(this.getAttribute('data-resident'));
          let content = `<div class="p-4">
            <p><strong>Name:</strong> ${resident.first_name} ${resident.middle_name ? resident.middle_name + " " : ""}${resident.last_name}</p>
            <p><strong>Birth Date:</strong> ${resident.birth_date}</p>
            <p><strong>Gender:</strong> ${resident.gender}</p>
            <p><strong>Email:</strong> ${resident.email}</p>
            <p><strong>Contact:</strong> ${resident.contact_number}</p>
            <p><strong>Marital Status:</strong> ${resident.marital_status}</p>
            <p><strong>Emergency Contact:</strong> ${resident.emergency_contact_name} (${resident.emergency_contact_number})</p>
            <p><strong>Address:</strong> ${resident.block_lot}, ${resident.phase}, ${resident.street}, ${resident.subdivision}</p>
          </div>`;
          document.getElementById('viewResidentContent').innerHTML = content;
          document.getElementById('viewResidentModal').style.display = 'flex';
        });
      });

      document.querySelectorAll('.editResidentBtn').forEach(button => {
        button.addEventListener('click', function() {
          let resident = JSON.parse(this.getAttribute('data-resident'));
          document.getElementById('edit_person_id').value = resident.person_id;
          document.getElementById('edit_first_name').value = resident.first_name;
          document.getElementById('edit_middle_name').value = resident.middle_name || "";
          document.getElementById('edit_last_name').value = resident.last_name;
          document.getElementById('edit_birth_date').value = resident.birth_date;
          document.getElementById('edit_gender').value = resident.gender;
          document.getElementById('edit_email').value = resident.email;
          document.getElementById('edit_contact_number').value = resident.contact_number;
          document.getElementById('edit_marital_status').value = resident.marital_status;
          document.getElementById('edit_senior_or_pwd').value = resident.senior_or_pwd;
          document.getElementById('edit_solo_parent').value = resident.solo_parent;
          document.getElementById('edit_emergency_contact_name').value = resident.emergency_contact_name;
          document.getElementById('edit_emergency_contact_number').value = resident.emergency_contact_number;
          document.getElementById('edit_emergency_contact_address').value = resident.emergency_contact_address;
          document.getElementById('edit_residency_type').value = resident.residency_type;
          document.getElementById('edit_years_in_san_rafael').value = resident.years_in_san_rafael;
          document.getElementById('edit_block_lot').value = resident.block_lot;
          document.getElementById('edit_phase').value = resident.phase;
          document.getElementById('edit_street').value = resident.street;
          document.getElementById('edit_subdivision').value = resident.subdivision;
          // Display current government ID from id_image_path if available.
          if (resident.id_image_path && resident.id_image_path.trim() !== "") {
              document.getElementById('govIdDisplay').innerHTML = `<a href="${resident.id_image_path}" target="_blank">View Current Government ID</a>`;
          } else {
              document.getElementById('govIdDisplay').innerText = "No government ID on file.";
          }
          document.getElementById('editResidentModal').style.display = 'flex';
        });
      });

      document.querySelectorAll('.deleteResidentBtn').forEach(button => {
        button.addEventListener('click', function() {
          let personId = this.getAttribute('data-id');
          Swal.fire({
            title: 'Delete Resident?',
            text: 'Are you sure you want to delete this resident?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              window.location.href = "../functions/resident_actions.php?delete_resident=" + personId;
            }
          });
        });
      });

      // Account View and Edit Actions
      document.querySelectorAll('.viewAccountBtn').forEach(button => {
        button.addEventListener('click', function() {
          let account = JSON.parse(this.getAttribute('data-account'));
          let content = `<div class="p-4">
            <p><strong>Email:</strong> ${account.email}</p>
            <p><strong>Role:</strong> ${account.role_id == 2 ? "Barangay Admin" : "Resident"}</p>
            <p><strong>Verification Status:</strong> ${account.isverify}</p>
          </div>`;
          Swal.fire({
            title: 'Account Details',
            html: content,
            icon: 'info'
          });
        });
      });

      document.querySelectorAll('.editAccountBtn').forEach(button => {
        button.addEventListener('click', function() {
          let account = JSON.parse(this.getAttribute('data-account'));
          document.getElementById('account_user_id').value = account.user_id;
          document.getElementById('edit_email').value = account.email;
          document.getElementById('edit_role').value = account.role_id;
          document.getElementById('editAccountModal').style.display = 'flex';
        });
      });

      // Auto-fill Resident Fields using OCR on picture upload
      const residentPicInput = document.getElementById('resident_pic');
      residentPicInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
          Swal.fire({
            title: 'Processing image...',
            text: 'Please wait while we extract text from the image.',
            allowOutsideClick: false,
            didOpen: () => {
              Swal.showLoading();
            }
          });
          Tesseract.recognize(file, 'eng').then(({ data: { text } }) => {
            Swal.close();
            console.log("OCR Extracted Text:", text);
            let lines = text.trim().split("\n").filter(line => line.trim() !== "");
            if (lines.length > 0) {
              let nameParts = lines[0].trim().split(" ");
              if (nameParts.length >= 2) {
                document.getElementById('first_name').value = nameParts[0];
                document.getElementById('last_name').value = nameParts[1];
              }
            }
          }).catch(err => {
            Swal.close();
            console.error("OCR Error:", err);
            alert("Failed to process image for text extraction.");
          });
        }
      });
    });
  </script>
</body>
</html>
