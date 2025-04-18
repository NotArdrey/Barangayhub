<?php
// dashboard.php
// -------------------------------------------------------------------
// 1) Include your DB config and any session logic
// -------------------------------------------------------------------
require_once "../config/dbconn.php";
require_once "../pages/header.php";

// Example: get barangay_id from session or default
$barangay_id = $_SESSION['barangay_id'] ?? 1;

// -------------------------------------------------------------------
// 2) Fetch some key metrics
//    - Adjust queries to match your schema
// -------------------------------------------------------------------

// Total Residents
$sql = "SELECT COUNT(*) AS total_residents
        FROM Users
        WHERE role_id = 3
          AND barangay_id = :bid";
$stmt = $pdo->prepare($sql);
$stmt->execute([':bid' => $barangay_id]);
$totalResidents = (int) $stmt->fetchColumn();

// Total Households (example logic)
$sql = "SELECT COUNT(DISTINCT a.user_id) 
        FROM Address a
        JOIN Users u ON a.user_id = u.user_id
        WHERE u.barangay_id = :bid";
$stmt = $pdo->prepare($sql);
$stmt->execute([':bid' => $barangay_id]);
$totalHouseholds = (int) $stmt->fetchColumn();

// Pending Document Requests
$sql = "SELECT COUNT(*) 
        FROM DocumentRequest dr
        JOIN Users u ON dr.user_id = u.user_id
        WHERE dr.status = 'Pending'
          AND u.barangay_id = :bid";
$stmt = $pdo->prepare($sql);
$stmt->execute([':bid' => $barangay_id]);
$pendingRequests = (int) $stmt->fetchColumn();

// Recent Activities in last 7 days (from AuditTrail)
$sql = "SELECT COUNT(*) 
        FROM AuditTrail a
        JOIN Users u ON a.admin_user_id = u.user_id
        WHERE u.barangay_id = :bid
          AND a.action_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
$stmt = $pdo->prepare($sql);
$stmt->execute([':bid' => $barangay_id]);
$recentActivities = (int) $stmt->fetchColumn();

// -------------------------------------------------------------------
// 3) Example: Gender Distribution for a Pie Chart
// -------------------------------------------------------------------
$sql = "SELECT gender, COUNT(*) AS count
        FROM Users
        WHERE role_id = 3
          AND barangay_id = :bid
        GROUP BY gender";
$stmt = $pdo->prepare($sql);
$stmt->execute([':bid' => $barangay_id]);
$genderData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// We'll store them in arrays for Chart.js
$genderLabels = [];
$genderCounts = [];
foreach ($genderData as $g) {
    if (!empty($g['gender'])) {
        $genderLabels[] = $g['gender'];
        $genderCounts[] = (int) $g['count'];
    }
}
// If no data (e.g. empty?), handle it gracefully
if (empty($genderLabels)) {
    $genderLabels = ['Male', 'Female', 'Others'];
    $genderCounts = [0,0,0];
}

// -------------------------------------------------------------------
// 4) Example: Document Requests by Type (Bar Chart)
// -------------------------------------------------------------------
$sql = "SELECT dt.document_name, COUNT(*) AS count
        FROM DocumentRequest dr
        JOIN DocumentType dt ON dr.document_type_id = dt.document_type_id
        JOIN Users u ON dr.user_id = u.user_id
        WHERE u.barangay_id = :bid
        GROUP BY dt.document_name
        ORDER BY count DESC
        LIMIT 5";  // e.g., top 5 doc types
$stmt = $pdo->prepare($sql);
$stmt->execute([':bid' => $barangay_id]);
$docTypeData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$docLabels = [];
$docCounts = [];
foreach ($docTypeData as $d) {
    $docLabels[] = $d['document_name'];
    $docCounts[] = (int) $d['count'];
}
if (empty($docLabels)) {
    // fallback to placeholders
    $docLabels = ['No Data'];
    $docCounts = [0];
}

// -------------------------------------------------------------------
// 5) Example: Recent Requests Table
//    We’ll show the last 5 pending or recently completed requests
// -------------------------------------------------------------------
$sql = "SELECT dr.document_request_id,
               dt.document_name,
               CONCAT(u.first_name, ' ', u.last_name) AS requester,
               dr.status,
               dr.request_date
        FROM DocumentRequest dr
        JOIN DocumentType dt ON dr.document_type_id = dt.document_type_id
        JOIN Users u ON dr.user_id = u.user_id
        WHERE u.barangay_id = :bid
        ORDER BY dr.request_date DESC
        LIMIT 5";
$stmt = $pdo->prepare($sql);
$stmt->execute([':bid' => $barangay_id]);
$recentRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------------------------------------------------------------------
// (Optionally) close out any leftover HTML from your header
// -------------------------------------------------------------------
?>
<section id="dashboard" class="p-4">
  <!-- Title -->
  <header class="mb-6">
    <h1 class="text-3xl font-bold text-blue-800">Barangay Dashboard</h1>
    <p class="text-gray-600">Overview of Barangay Activities and Statistics</p>
  </header>

  <!-- Row of Key Metric Cards -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <!-- Residents -->
    <div class="bg-white p-6 rounded-lg shadow transition-shadow hover:shadow-lg">
      <div class="text-3xl font-bold text-blue-800">
        <?php echo $totalResidents; ?>
      </div>
      <div class="mt-2 text-gray-600">Residents</div>
    </div>
    <!-- Households -->
    <div class="bg-white p-6 rounded-lg shadow transition-shadow hover:shadow-lg">
      <div class="text-3xl font-bold text-blue-800">
        <?php echo $totalHouseholds; ?>
      </div>
      <div class="mt-2 text-gray-600">Households</div>
    </div>
    <!-- Pending Requests -->
    <div class="bg-white p-6 rounded-lg shadow transition-shadow hover:shadow-lg">
      <div class="text-3xl font-bold text-blue-800">
        <?php echo $pendingRequests; ?>
      </div>
      <div class="mt-2 text-gray-600">Pending Requests</div>
    </div>
    <!-- Recent Activities -->
    <div class="bg-white p-6 rounded-lg shadow transition-shadow hover:shadow-lg">
      <div class="text-3xl font-bold text-blue-800">
        <?php echo $recentActivities; ?>
      </div>
      <div class="mt-2 text-gray-600">Recent Activities (7 Days)</div>
    </div>
  </div>

  <!-- Charts Section -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- Pie/Donut Chart: Gender Distribution -->
    <div class="bg-white p-6 rounded-lg shadow transition-shadow hover:shadow-lg">
      <h2 class="text-xl font-semibold text-gray-800 mb-4">Gender Distribution</h2>
      <canvas id="genderChart" width="400" height="300"></canvas>
    </div>

    <!-- Bar Chart: Document Requests by Type -->
    <div class="bg-white p-6 rounded-lg shadow transition-shadow hover:shadow-lg">
      <h2 class="text-xl font-semibold text-gray-800 mb-4">Top Document Requests</h2>
      <canvas id="docTypeChart" width="400" height="300"></canvas>
    </div>
  </div>

  <!-- Recent Requests Table -->
  <div class="bg-white p-6 rounded-lg shadow transition-shadow hover:shadow-lg">
    <h2 class="text-xl font-semibold text-gray-800 mb-4">Recent Requests</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full border border-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 border-b text-left text-gray-700">Request ID</th>
            <th class="px-4 py-2 border-b text-left text-gray-700">Requester</th>
            <th class="px-4 py-2 border-b text-left text-gray-700">Document</th>
            <th class="px-4 py-2 border-b text-left text-gray-700">Status</th>
            <th class="px-4 py-2 border-b text-left text-gray-700">Date</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!empty($recentRequests)): ?>
            <?php foreach($recentRequests as $req): ?>
            <tr class="hover:bg-gray-50">
              <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($req['document_request_id']); ?></td>
              <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($req['requester']); ?></td>
              <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($req['document_name']); ?></td>
              <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($req['status']); ?></td>
              <td class="px-4 py-2 border-b"><?php echo htmlspecialchars($req['request_date']); ?></td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="px-4 py-2 text-center text-gray-600">No recent requests.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- 6) Include Chart.js. 
     If you're offline, reference a local copy, e.g.:
     <script src="/js/chart.umd.js"></script> 
     Otherwise (online) you could do: 
     <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
-->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// 7) Build the Gender Distribution Chart
const genderCtx = document.getElementById('genderChart').getContext('2d');
const genderChart = new Chart(genderCtx, {
  type: 'doughnut', // or 'pie'
  data: {
    labels: <?php echo json_encode($genderLabels); ?>,
    datasets: [{
      data: <?php echo json_encode($genderCounts); ?>,
      // Some arbitrary background colors
      backgroundColor: [
        'rgba(54, 162, 235, 0.7)',  // e.g. Male
        'rgba(255, 99, 132, 0.7)',  // e.g. Female
        'rgba(255, 206, 86, 0.7)',  // e.g. Others
      ],
      borderWidth: 1
    }]
  },
  options: {
    plugins: {
      legend: {
        position: 'bottom'
      }
    }
  }
});

// 8) Build the Document Requests Bar Chart
const docCtx = document.getElementById('docTypeChart').getContext('2d');
const docTypeChart = new Chart(docCtx, {
  type: 'bar',
  data: {
    labels: <?php echo json_encode($docLabels); ?>,
    datasets: [{
      label: 'Requests',
      data: <?php echo json_encode($docCounts); ?>,
      backgroundColor: 'rgba(75, 192, 192, 0.6)',
      borderWidth: 1
    }]
  },
  options: {
    scales: {
      y: {
        beginAtZero: true,
        title: {
          display: true,
          text: 'Number of Requests'
        }
      },
      x: {
        title: {
          display: true,
          text: 'Document Type'
        }
      }
    },
    plugins: {
      legend: {
        display: false
      }
    }
  }
});
</script>

</main>
</body>
</html>
