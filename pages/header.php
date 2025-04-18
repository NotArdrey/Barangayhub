<?php
// pages/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../config/dbconn.php';

$bid = $_SESSION['barangay_id'] ?? null;
if ($bid) {
    $stmt = $pdo->prepare("SELECT barangay_name FROM Barangay WHERE barangay_id = :bid");
    $stmt->execute([':bid' => $bid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $barangayName = $row['barangay_name'] ?? 'Unknown Barangay';
} else {
    $barangayName = 'Undefined Barangay';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Barangay Administration System</title>

  <!-- Tailwind & Flowbite -->
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.css" rel="stylesheet" />

  <!-- SweetAlert2 & Tesseract.js -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://unpkg.com/tesseract.js@v2.1.5/dist/tesseract.min.js"></script>

  <style>
    .nav-link {
      display: flex;
      align-items: center;
      padding: 0.75rem;
      border-radius: 0.5rem;
      transition: background-color 0.2s ease, color 0.2s ease;
    }
    .nav-link:hover {
      background-color: #f3f4f6;
      color: #1e40af;
    }
    .icon-container {
      width: 2.5rem;
      height: 2.5rem;
      background-color: #f9fafb;
      border-radius: 0.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: 0.75rem;
      transition: transform 0.2s ease, background-color 0.2s ease;
      overflow: visible;
    }
    .icon-container svg {
      display: block;
      width: 1.25rem;
      height: 1.25rem;
    }
    .nav-link:hover .icon-container {
      transform: scale(1.05);
      background-color: #e0e7ff;
    }
    .icon-container.logout {
      background-color: #fff5f5;
    }
    .nav-link:hover .icon-container.logout {
      background-color: #fee2e2;
    }
    .modal {
      position: fixed;
      top: 0; left: 0;
      width: 100%; height: 100%;
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

  <!-- Sidebar Navigation -->
  <aside class="fixed left-0 top-0 h-screen w-64 bg-white border-r border-gray-200 p-4 shadow-md">
    <div class="mb-8 px-2">
      <h2 class="text-2xl font-bold text-blue-800"><?= htmlspecialchars($barangayName) ?></h2>
      <p class="text-sm text-gray-600">Administration System</p>
    </div>
    <nav aria-label="Main Navigation">
      <ul class="space-y-1">

        <!-- Dashboard -->
        <li>
          <a href="../pages/barangay_admin_dashboard.php" class="nav-link">
            <span class="icon-container">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6" />
              </svg>
            </span>
            <span class="font-medium text-gray-700">Dashboard</span>
          </a>
        </li>

        <!-- Residents -->
        <li>
          <a href="../pages/residents.php" class="nav-link">
            <span class="icon-container">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M12 6.375a3.375 3.375 0 1 1-6.75 0 3 3 0 0 1 6.75 0Z" />
              </svg>
            </span>
            <span class="font-medium text-gray-700">Residents</span>
          </a>
        </li>

        <!-- Document Requests -->
        <li>
          <a href="../pages/doc_request.php" class="nav-link">
            <span class="icon-container">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-3-3v6M12 3v4m6 2h4M3 12H7m0 0v6m0-6v-6" />
              </svg>
            </span>
            <span class="font-medium text-gray-700">Document Requests</span>
          </a>
        </li>

        <!-- Blotter -->
        <li>
          <a href="../pages/blotter.php" class="nav-link">
            <span class="icon-container">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7" />
              </svg>
            </span>
            <span class="font-medium text-gray-700">Blotter</span>
          </a>
        </li>

        <!-- Events -->
        <li>
          <a href="../pages/events.php" class="nav-link">
            <span class="icon-container">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10m-12 8h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2z" />
              </svg>
            </span>
            <span class="font-medium text-gray-700">Events</span>
          </a>
        </li>

        <!-- Audit Trail -->
        <li>
          <a href="../pages/audit_trail.php" class="nav-link">
            <span class="icon-container">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5h6M9 9h6M9 13h6M9 17h6" />
              </svg>
            </span>
            <span class="font-medium text-gray-700">Audit Trail</span>
          </a>
        </li>
        <!-- Logout -->
        <li>
          <form id="logoutForm" action="../functions/logout.php" method="post">
            <button type="button" id="logoutBtn" class="nav-link">
              <span class="icon-container logout">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1m0-9V5" />
                </svg>
              </span>
              <span class="font-medium text-gray-700">Logout</span>
            </button>
          </form>
        </li>

      </ul>
    </nav>
  </aside>

  <main class="ml-64 p-8 space-y-8">
    <script>
      document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('logoutBtn').addEventListener('click', () => {
          Swal.fire({
            title: 'Ready to leave?',
            text: "Select 'Logout' below if you are ready to end your current session.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Logout',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              document.getElementById('logoutForm').submit();
            }
          });
        });
      });
    </script>
