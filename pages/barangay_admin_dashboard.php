<?php
session_start();
require "../config/dbconn.php";

// Ensure that only a Barangay Admin (role_id=2) can access this page.
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    header("Location: ../pages/index.php");
    exit;
}

// Get the barangay name from session (set during login) or a default value.
$barangayName = isset($_SESSION['barangay_name']) ? $_SESSION['barangay_name'] : "Undefined Barangay";

// Retrieve the corresponding barangay_id from the Barangay table.
$stmt = $pdo->prepare("SELECT barangay_id FROM Barangay WHERE barangay_name = :barangay_name LIMIT 1");
$stmt->execute([':barangay_name' => $barangayName]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$barangay_id = $result ? $result['barangay_id'] : null;

// --- Dashboard Queries ---
// Query for the total residents registered in this barangay.
$totalResidents = 0;
if ($barangay_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM Person WHERE barangay_id = :barangay_id");
    $stmt->execute([':barangay_id' => $barangay_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalResidents = $res ? $res['total'] : 0;
}

// Query for the total households by counting unique block_lot values in the Address table.
$totalHouseholds = 0;
if ($barangay_id) {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT block_lot) as households FROM Address WHERE barangay_id = :barangay_id");
    $stmt->execute([':barangay_id' => $barangay_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalHouseholds = $res ? $res['households'] : 0;
}

// Query pending requests from DocumentRequest records for persons in this barangay.
$pendingRequests = 0;
if ($barangay_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as pending 
                           FROM DocumentRequest 
                           WHERE status = 'pending' 
                           AND person_id IN (SELECT person_id FROM Person WHERE barangay_id = :barangay_id)");
    $stmt->execute([':barangay_id' => $barangay_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    $pendingRequests = $res ? $res['pending'] : 0;
}

// Query recent audit trail activities logged by this admin.
$recentActivities = 0;
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM AuditTrail WHERE admin_user_id = :admin_user_id");
$stmt->execute([':admin_user_id' => $_SESSION['user_id']]);
$res = $stmt->fetch(PDO::FETCH_ASSOC);
$recentActivities = $res ? $res['total'] : 0;

// --- Blotter Feature Processing ---
// When the admin submits the blotter record form.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['blotter_submit'])) {
    // Retrieve complaint text and location.
    $complaint = isset($_POST['complaint']) ? trim($_POST['complaint']) : "";
    $location = isset($_POST['location']) ? trim($_POST['location']) : "";
    
    // Handle file upload (if any).
    $uploadedFilePath = "";
    if (isset($_FILES['complaint_file']) && $_FILES['complaint_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = "../uploads/blotter/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $fileName = basename($_FILES['complaint_file']['name']);
        $targetFile = $uploadDir . time() . "_" . $fileName;
        if (move_uploaded_file($_FILES['complaint_file']['tmp_name'], $targetFile)) {
            $uploadedFilePath = $targetFile;
        }
    }
    // Build a complete description—combining text input with the file path if a file was uploaded.
    $description = $complaint;
    if ($uploadedFilePath != "") {
        $description .= "\nUploaded file: " . $uploadedFilePath;
    }
    
    // Insert the new blotter case record into the BlotterCase table.
    $stmt = $pdo->prepare("INSERT INTO BlotterCase (date_reported, location, description, status, barangay_id) VALUES (NOW(), :location, :description, 'Pending', :barangay_id)");
    $stmt->execute([
        ':location' => $location,
        ':description' => $description,
        ':barangay_id' => $barangay_id
    ]);
    $blotter_case_id = $pdo->lastInsertId();

    // Log the action to the AuditTrail table.
    $auditStmt = $pdo->prepare("INSERT INTO AuditTrail (admin_user_id, action, table_name, record_id, description) VALUES (:admin_user_id, 'Add', 'BlotterCase', :record_id, :description)");
    $auditStmt->execute([
        ':admin_user_id' => $_SESSION['user_id'],
        ':record_id'   => $blotter_case_id,
        ':description' => "Added new blotter case with ID " . $blotter_case_id
    ]);
    // Notify the admin and reload the page.
    echo "<script>alert('Blotter record added successfully.'); window.location.href='" . $_SERVER['PHP_SELF'] . "';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Barangay Administration System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.css" rel="stylesheet" />
  <style>
    /* Sidebar active link customization */
    .nav-link.active {
      background-color: #f3f4f6;
      color: #1e40af;
    }
    .nav-link.active .icon-container {
      background-color: #1e40af;
    }
    /* Smooth transition and slight scale on hover for icons */
    .icon-container {
      transition: transform 0.3s ease, background-color 0.3s ease;
    }
    .nav-link:hover .icon-container {
      transform: scale(1.05);
    }
  </style>
</head>
<body class="bg-gray-50">
  <!-- Sidebar -->
  <aside class="fixed left-0 top-0 h-screen w-64 bg-white border-r border-gray-200 p-4 shadow-md">
    <div class="mb-8 px-2">
      <!-- Dynamic barangay name in header -->
      <h2 class="text-2xl font-bold text-blue-800"><?php echo htmlspecialchars($barangayName); ?></h2>
      <p class="text-sm text-gray-600">Administration System</p>
    </div>
    <nav aria-label="Main Navigation">
      <ul class="space-y-1">
        <!-- Dashboard -->
        <li>
          <a href="#dashboard" class="nav-link flex items-center p-3 rounded-lg hover:bg-gray-100 transition-colors group">
            <span class="icon-container p-2 rounded-lg bg-gray-100 group-hover:bg-blue-100 transition-colors mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-600 group-hover:text-blue-800">
                <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
              </svg>
            </span>
            <span class="text-gray-700 group-hover:text-blue-800 font-medium">Dashboard</span>
          </a>
        </li>
        <!-- Residents -->
        <li>
          <a href="#residents" class="nav-link flex items-center p-3 rounded-lg hover:bg-gray-100 transition-colors group">
            <span class="icon-container p-2 rounded-lg bg-gray-100 group-hover:bg-blue-100 transition-colors mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-600 group-hover:text-blue-800">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
              </svg>
            </span>
            <span class="text-gray-700 group-hover:text-blue-800 font-medium">Residents</span>
          </a>
        </li>
        <!-- Blotter -->
        <li>
          <a href="#blotter" class="nav-link flex items-center p-3 rounded-lg hover:bg-gray-100 transition-colors group">
            <span class="icon-container p-2 rounded-lg bg-gray-100 group-hover:bg-blue-100 transition-colors mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-600 group-hover:text-blue-800">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
              </svg>
            </span>
            <span class="text-gray-700 group-hover:text-blue-800 font-medium">Blotter</span>
          </a>
        </li>
        <!-- Permits -->
        <li>
          <a href="#permits" class="nav-link flex items-center p-3 rounded-lg hover:bg-gray-100 transition-colors group">
            <span class="icon-container p-2 rounded-lg bg-gray-100 group-hover:bg-blue-100 transition-colors mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-600 group-hover:text-blue-800">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
              </svg>
            </span>
            <span class="text-gray-700 group-hover:text-blue-800 font-medium">Permits</span>
          </a>
        </li>
        <!-- Announcements -->
        <li>
          <a href="#announcements" class="nav-link flex items-center p-3 rounded-lg hover:bg-gray-100 transition-colors group">
            <span class="icon-container p-2 rounded-lg bg-gray-100 group-hover:bg-blue-100 transition-colors mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-600 group-hover:text-blue-800">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
              </svg>
            </span>
            <span class="text-gray-700 group-hover:text-blue-800 font-medium">Announcements</span>
          </a>
        </li>
        <!-- Accounts -->
        <li>
          <a href="#accounts" class="nav-link flex items-center p-3 rounded-lg hover:bg-gray-100 transition-colors group">
            <span class="icon-container p-2 rounded-lg bg-gray-100 group-hover:bg-blue-100 transition-colors mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-600 group-hover:text-blue-800">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
              </svg>
            </span>
            <span class="text-gray-700 group-hover:text-blue-800 font-medium">Accounts</span>
          </a>
        </li>
        <!-- Settings -->
        <li>
          <a href="#settings" class="nav-link flex items-center p-3 rounded-lg hover:bg-gray-100 transition-colors group">
            <span class="icon-container p-2 rounded-lg bg-gray-100 group-hover:bg-blue-100 transition-colors mr-3">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-gray-600 group-hover:text-blue-800">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </span>
            <span class="text-gray-700 group-hover:text-blue-800 font-medium">Settings</span>
          </a>
        </li>
      </ul>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="ml-64 p-8 space-y-8">
    <!-- Dashboard Section -->
    <section id="dashboard">
      <header class="mb-6">
        <h1 class="text-3xl font-bold text-blue-800">Dashboard</h1>
      </header>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <!-- Stat Card 1: Total Residents -->
        <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow">
          <div class="text-3xl font-bold text-blue-800"><?php echo $totalResidents; ?></div>
          <div class="mt-2 text-gray-600">Total Residents</div>
        </div>
        <!-- Stat Card 2: Households -->
        <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow">
          <div class="text-3xl font-bold text-blue-800"><?php echo $totalHouseholds; ?></div>
          <div class="mt-2 text-gray-600">Households</div>
        </div>
        <!-- Stat Card 3: Pending Requests -->
        <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow">
          <div class="text-3xl font-bold text-blue-800"><?php echo $pendingRequests; ?></div>
          <div class="mt-2 text-gray-600">Pending Requests</div>
        </div>
        <!-- Stat Card 4: Recent Activities -->
        <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow">
          <div class="text-3xl font-bold text-blue-800"><?php echo $recentActivities; ?></div>
          <div class="mt-2 text-gray-600">Recent Activities</div>
        </div>
      </div>
    </section>

    <!-- Residents Section -->
    <section id="residents" class="hidden">
      <header class="mb-6">
        <h1 class="text-3xl font-bold text-blue-800">Residents Management</h1>
      </header>
      <div class="bg-white p-6 rounded-lg shadow">
        <div class="mb-4">
          <input type="text" id="residentSearch" placeholder="Search residents..." class="w-full p-3 border rounded-lg" />
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full border border-gray-200" id="residentsTable">
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
              // Dynamically fetch residents from the admin's barangay.
              $stmt = $pdo->prepare("SELECT p.first_name, p.middle_name, p.last_name, p.birth_date, a.block_lot 
                                     FROM Person p JOIN Address a ON p.person_id = a.person_id 
                                     WHERE p.barangay_id = :barangay_id");
              $stmt->execute([':barangay_id' => $barangay_id]);
              $residents = $stmt->fetchAll(PDO::FETCH_ASSOC);
              foreach ($residents as $resident) {
                  $fullName = $resident['first_name'] . " " . ($resident['middle_name'] ? $resident['middle_name'] . " " : "") . $resident['last_name'];
                  // Calculate age if the birth_date is set.
                  $age = '';
                  if ($resident['birth_date']) {
                      $birthDate = new DateTime($resident['birth_date']);
                      $today = new DateTime();
                      $age = $today->diff($birthDate)->y;
                  }
                  echo "<tr class='hover:bg-gray-50'>";
                  echo "<td class='px-4 py-2 border-b'>" . htmlspecialchars($fullName) . "</td>";
                  echo "<td class='px-4 py-2 border-b'>" . htmlspecialchars($age) . "</td>";
                  echo "<td class='px-4 py-2 border-b'>" . htmlspecialchars($resident['block_lot']) . "</td>";
                  echo "<td class='px-4 py-2 border-b'><span class='px-3 py-1 bg-green-100 text-green-800 rounded'>Active</span></td>";
                  echo "<td class='px-4 py-2 border-b space-x-2'>";
                  echo "<button class='px-3 py-1 text-blue-600 hover:text-blue-800'>View</button>";
                  echo "<button class='px-3 py-1 text-yellow-600 hover:text-yellow-800'>Edit</button>";
                  echo "<button class='px-3 py-1 text-red-600 hover:text-red-800'>Delete</button>";
                  echo "</td>";
                  echo "</tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- Blotter Section -->
    <section id="blotter" class="hidden">
      <header class="mb-6">
        <h1 class="text-3xl font-bold text-blue-800">Blotter Records</h1>
      </header>
      <div class="bg-white p-6 rounded-lg shadow">
        <!-- Form to Add a New Blotter Record -->
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
          <div>
            <label class="block text-gray-700">Location</label>
            <input type="text" name="location" placeholder="Enter incident location" class="w-full p-3 border rounded-lg" required />
          </div>
          <div>
            <label class="block text-gray-700">Complaint Details</label>
            <textarea name="complaint" placeholder="Type your complaint record here" rows="5" class="w-full p-3 border rounded-lg"></textarea>
          </div>
          <div>
            <label class="block text-gray-700">Or Upload Document</label>
            <input type="file" name="complaint_file" accept=".doc,.docx" class="w-full p-3 border rounded-lg" />
          </div>
          <button type="submit" name="blotter_submit" class="px-4 py-2 bg-blue-800 text-white rounded-lg hover:bg-blue-900 transition-colors">Submit Blotter Record</button>
        </form>
        <hr class="my-6" />
        <!-- Display existing blotter records for this barangay -->
        <div>
          <h2 class="text-xl font-bold text-blue-800 mb-4">Existing Blotter Records</h2>
          <div class="overflow-x-auto">
            <table class="min-w-full border border-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-2 border-b text-left text-gray-700">Date Reported</th>
                  <th class="px-4 py-2 border-b text-left text-gray-700">Location</th>
                  <th class="px-4 py-2 border-b text-left text-gray-700">Description</th>
                  <th class="px-4 py-2 border-b text-left text-gray-700">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $stmt = $pdo->prepare("SELECT date_reported, location, description, status FROM BlotterCase WHERE barangay_id = :barangay_id ORDER BY date_reported DESC");
                $stmt->execute([':barangay_id' => $barangay_id]);
                $blotterRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($blotterRecords as $record) {
                    echo "<tr class='hover:bg-gray-50'>";
                    echo "<td class='px-4 py-2 border-b'>" . htmlspecialchars($record['date_reported']) . "</td>";
                    echo "<td class='px-4 py-2 border-b'>" . htmlspecialchars($record['location']) . "</td>";
                    echo "<td class='px-4 py-2 border-b'>" . nl2br(htmlspecialchars($record['description'])) . "</td>";
                    echo "<td class='px-4 py-2 border-b'>" . htmlspecialchars($record['status']) . "</td>";
                    echo "</tr>";
                }
                ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </section>

    <!-- Permits Section -->
    <section id="permits" class="hidden">
      <header class="mb-6">
        <h1 class="text-3xl font-bold text-blue-800">Permits Management</h1>
      </header>
      <div class="bg-white p-6 rounded-lg shadow">
        <p class="text-gray-600">Here you can manage permit requests and view application details.</p>
      </div>
    </section>

    <!-- Announcements Section -->
    <section id="announcements" class="hidden">
      <header class="mb-6">
        <h1 class="text-3xl font-bold text-blue-800">Announcements</h1>
      </header>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow">
          <h2 class="text-xl font-bold text-blue-800 mb-2">Maintenance Downtime</h2>
          <p class="text-gray-600">Scheduled maintenance will occur on 2025-04-15 from 12 AM to 4 AM.</p>
        </div>
        <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition-shadow">
          <h2 class="text-xl font-bold text-blue-800 mb-2">New Registration Drive</h2>
          <p class="text-gray-600">A registration drive is planned for 2025-05-01. Stay tuned for more details.</p>
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
              // For demonstration, list users (excluding Super Admin) along with action buttons.
              $stmt = $pdo->prepare("SELECT email, role_id, isverify FROM Users WHERE role_id <> 1");
              $stmt->execute();
              $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
              foreach ($users as $user) {
                  echo "<tr class='hover:bg-gray-50'>";
                  echo "<td class='px-4 py-2 border-b'>" . htmlspecialchars($user['email']) . "</td>";
                  echo "<td class='px-4 py-2 border-b'>" . ($user['role_id'] == 2 ? "Barangay Admin" : "Resident") . "</td>";
                  echo "<td class='px-4 py-2 border-b'>" . htmlspecialchars($user['isverify']) . "</td>";
                  echo "<td class='px-4 py-2 border-b space-x-2'>";
                  echo "<button class='px-3 py-1 text-blue-600 hover:text-blue-800'>View</button>";
                  echo "<button class='px-3 py-1 text-yellow-600 hover:text-yellow-800'>Edit</button>";
                  echo "</td>";
                  echo "</tr>";
              }
              ?>
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
            <input type="text" placeholder="Enter site title" class="w-full p-3 border rounded-lg" />
          </div>
          <div>
            <label class="block text-gray-700">Contact Email</label>
            <input type="email" placeholder="Enter contact email" class="w-full p-3 border rounded-lg" />
          </div>
          <!-- Additional settings fields can be added here -->
          <button type="submit" class="px-4 py-2 bg-blue-800 text-white rounded-lg hover:bg-blue-900 transition-colors">Save Changes</button>
        </form>
      </div>
    </section>
  </main>

  <script>
    // Navigation: reveal the corresponding section and set active links on click.
    document.addEventListener('DOMContentLoaded', function() {
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

      // Activate the Dashboard by default.
      const defaultLink = document.querySelector('.nav-link[href="#dashboard"]');
      setActiveLink(defaultLink);
      setActiveSection('#dashboard');
    });
  </script>
</body>
</html>
