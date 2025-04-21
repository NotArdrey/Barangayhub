<?php
/**
 * audit_trail.php ― complete page
 * Lists all audit‑trail entries for the current barangay with
 *   ▸ global search
 *   ▸ per‑column sorting
 *   ▸ filters (action, table, user, date range)
 * Assumes Tailwind CSS & Font Awesome are available.
 */

require_once "../pages/header.php";
require "../config/dbconn.php";   // must create $pdo

// ── Auth guard ─────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] < 2) {
    header("Location: ../pages/index.php");
    exit;
}

// ── Fetch data ─────────────────────────────────────────────────────────
$bid = $_SESSION['barangay_id'];
$sql = "
    SELECT  a.*,
            CONCAT(u.first_name, ' ', u.last_name) AS admin_name
    FROM    AuditTrail a
    JOIN    Users u ON u.user_id = a.admin_user_id
    WHERE   u.barangay_id = :bid
    ORDER   BY a.action_timestamp DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':bid' => $bid]);
$auditRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Build filter options ──────────────────────────────────────────────
$actions = $tables = $users = [];
foreach ($auditRecords as $rec) {
    if ($rec['action'])      $actions[$rec['action']]       = true;
    if ($rec['table_name'])  $tables[$rec['table_name']]     = true;
    if ($rec['admin_name'])  $users[$rec['admin_name']]      = true;
}
$actions = array_keys($actions);
$tables  = array_keys($tables);
$users   = array_keys($users);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Audit Trail</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <!-- Tailwind & Font Awesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 p-4 md:p-6">

<section id="auditTrail" class="max-w-7xl mx-auto">
  <header class="mb-6 space-y-4">
    <div class="flex justify-between items-center flex-wrap gap-4">
      <h1 class="text-3xl font-bold text-blue-800">
        <i class="text-3xl font-bold text-blue-800"></i>Audit Trail
      </h1>
      <!-- Global search -->
      <div class="w-full md:w-64">
        <div class="relative">
          <input id="auditSearch" type="text" placeholder="Search…"
                 class="w-full pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
          <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 overflow-x-auto">
      <div class="flex flex-wrap items-end gap-4">

        <!-- Action Filter -->
        <div class="min-w-[160px] flex-1 sm:flex-none">
          <label for="actionFilter" class="block text-sm font-medium text-gray-700 mb-1">Action</label>
          <select id="actionFilter"
                  class="w-full p-2 border rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
            <option value="">All Actions</option>
            <?php foreach ($actions as $act): ?>
              <option><?= htmlspecialchars($act) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Table Filter -->
        <div class="min-w-[160px] flex-1 sm:flex-none">
          <label for="tableFilter" class="block text-sm font-medium text-gray-700 mb-1">Table</label>
          <select id="tableFilter"
                  class="w-full p-2 border rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
            <option value="">All Tables</option>
            <?php foreach ($tables as $tbl): ?>
              <option><?= htmlspecialchars($tbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- User Filter -->
        <div class="min-w-[160px] flex-1 sm:flex-none">
          <label for="userFilter" class="block text-sm font-medium text-gray-700 mb-1">User</label>
          <select id="userFilter"
                  class="w-full p-2 border rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
            <option value="">All Users</option>
            <?php foreach ($users as $usr): ?>
              <option><?= htmlspecialchars($usr) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Date Range -->
        <div class="min-w-[220px] flex-1 sm:flex-none">
          <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
          <div class="flex gap-2">
            <input id="startDate" type="date"
                   class="flex-1 p-2 border rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
            <input id="endDate"   type="date"
                   class="flex-1 p-2 border rounded-md text-sm focus:ring-blue-500 focus:border-blue-500">
          </div>
        </div>

        <!-- Apply / Reset -->
        <div class="flex gap-2 flex-shrink-0">
          <button id="applyFilters" type="button"
                  class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-md text-sm font-medium">
            Apply
          </button>
          <button type="button" onclick="resetFilters()"
                  class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-sm font-medium">
            Reset
          </button>
        </div>

      </div>
    </div>
  </header>

  <!-- Audit Table -->
  <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
  <div class="overflow-x-auto">
    <table id="auditTable" class="min-w-full divide-y divide-gray-200">
      <thead class="bg-gray-50">
        <tr>
          <?php
            $cols     = ['Action','Table','User','Description','Timestamp'];
            $sortable = [true,true,true,false,true];
            foreach ($cols as $i => $col): ?>
            <th
              scope="col"
              class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider
                     <?= $sortable[$i] ? 'sortable cursor-pointer' : '' ?>">
              <?= $col ?>
              <?php if ($sortable[$i]): ?>
                <i class="sort-arrow fas fa-sort ml-1"></i>
              <?php endif; ?>
            </th>
        <?php endforeach; ?>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-200">
        <?php if ($auditRecords): ?>
          <?php foreach ($auditRecords as $rec): ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 text-sm font-medium text-gray-900 border-b">
              <?= htmlspecialchars($rec['action']) ?>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600 border-b">
              <?= htmlspecialchars($rec['table_name']) ?>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600 border-b">
              <?= htmlspecialchars($rec['admin_name']) ?>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate border-b">
              <?= htmlspecialchars($rec['description']) ?>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500 whitespace-nowrap border-b"
                data-ts="<?= htmlspecialchars($rec['action_timestamp']) ?>">
              <?= date('M j, Y H:i', strtotime($rec['action_timestamp'])) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="5" class="px-4 py-6 text-center text-gray-500">
              No audit records found.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</section>

<script>
  // — Global search triggers filter pass —
  document.getElementById('auditSearch')
    .addEventListener('keyup', applyFilters);

  // — Set up sortable headers —
  const headers = document.querySelectorAll('#auditTable thead th.sortable');
  headers.forEach((th, idx) => {
    th.addEventListener('click', () => {
      // Reset all arrows
      headers.forEach(h => {
        h.dataset.sortDir = '';
        h.querySelector('.sort-arrow').className = 'sort-arrow fas fa-sort';
      });
      // Toggle this column
      const dir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';
      th.dataset.sortDir = dir;
      th.querySelector('.sort-arrow')
         .className = dir === 'asc'
           ? 'sort-arrow fas fa-sort-up'
           : 'sort-arrow fas fa-sort-down';
      sortTable(idx, dir);
    });
  });

  function sortTable(colIdx, dir) {
    const tbody = document.querySelector('#auditTable tbody');
    const rows  = Array.from(tbody.querySelectorAll('tr'))
                       .filter(r => r.style.display !== 'none');
    rows.sort((a, b) => {
      let A = colIdx === 4
              ? new Date(a.children[colIdx].dataset.ts)
              : a.children[colIdx].innerText.toLowerCase();
      let B = colIdx === 4
              ? new Date(b.children[colIdx].dataset.ts)
              : b.children[colIdx].innerText.toLowerCase();
      return (A > B ? 1 : A < B ? -1 : 0) * (dir === 'asc' ? 1 : -1);
    }).forEach(r => tbody.appendChild(r));
  }

  // — Apply all filters: text, selects, dates —
  function applyFilters() {
    const term      = document.getElementById('auditSearch').value.toLowerCase();
    const actionVal = document.getElementById('actionFilter').value.toLowerCase();
    const tableVal  = document.getElementById('tableFilter').value.toLowerCase();
    const userVal   = document.getElementById('userFilter').value.toLowerCase();
    const sd        = document.getElementById('startDate').value;
    const ed        = document.getElementById('endDate').value;
    const start     = sd ? new Date(sd) : null;
    const end       = ed ? new Date(ed) : null;

    document.querySelectorAll('#auditTable tbody tr').forEach(row => {
      const txt   = row.textContent.toLowerCase();
      const act   = row.children[0].innerText.toLowerCase();
      const tbl   = row.children[1].innerText.toLowerCase();
      const usr   = row.children[2].innerText.toLowerCase();
      const ts    = new Date(row.children[4].dataset.ts);
      let   show  = true;

      if (term && !txt.includes(term))              show = false;
      if (actionVal && act !== actionVal)            show = false;
      if (tableVal  && tbl !== tableVal)             show = false;
      if (userVal   && usr !== userVal)              show = false;
      if (start && ts < start)                       show = false;
      if (end   && ts > end)                         show = false;

      row.style.display = show ? '' : 'none';
    });
  }

  // — Reset filters back to defaults —
  function resetFilters() {
    ['auditSearch','actionFilter','tableFilter','userFilter','startDate','endDate']
      .forEach(id => document.getElementById(id).value = '');
    applyFilters();
  }

  // — Initialize on load —
  applyFilters();
</script>

</body>
</html>
