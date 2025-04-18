<?php
session_start();
// doc_request.php
require "../vendor/autoload.php";
require "../config/dbconn.php";
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception; // If you want to catch PHPMailer exceptions




// 3) Make sure the user is actually logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] < 2) {
    header("Location: ../pages/index.php");
    exit;
}

// 4) Safe to read these now
$current_admin_id = $_SESSION['user_id'];
$bid              = $_SESSION['barangay_id'];
$role             = $_SESSION['role_id'];


/**
 * Helper function to insert into AuditTrail
 * Adjust or refine as desired.
 */
function logAuditTrail($pdo, $adminId, $action, $tableName, $recordId, $description) {
    $stmtAudit = $pdo->prepare("
        INSERT INTO AuditTrail (admin_user_id, action, table_name, record_id, description)
        VALUES (:admin_id, :action, :tbl, :rid, :desc)
    ");
    $stmtAudit->execute([
        ':admin_id' => $adminId,
        ':action'   => $action,
        ':tbl'      => $tableName,
        ':rid'      => $recordId,
        ':desc'     => $description
    ]);
}

// ------------------------------------------------------
// 2. Check if we have an AJAX or direct action; if so, return JSON or process
// ------------------------------------------------------
if (isset($_GET['action'])) {

    // Set up JSON header (for AJAX responses); no HTML before this.
    header('Content-Type: application/json');
    $action   = $_GET['action'];
    $response = ['success' => false, 'message' => ''];

    // Safely fetch the request ID
    $reqId = isset($_GET['id']) ? intval($_GET['id']) : 0;

    try {
        // ---------------------------------------
        // (A) VIEW DETAILS OF A SPECIFIC REQUEST
        // ---------------------------------------
        if ($action === 'view_doc_request') {
            $stmt = $pdo->prepare("
                SELECT 
                    dr.document_request_id,
                    dr.request_date,
                    dr.status,
                    dr.delivery_method,
                    dr.remarks AS request_remarks,
                    dt.document_name,
                    -- Fetch all relevant user details
                    u.user_id,
                    u.email,
                    u.contact_number,
                    u.birth_date,
                    u.gender,
                    u.marital_status,
                    u.emergency_contact_name,
                    u.emergency_contact_number,
                    u.emergency_contact_address,
                    u.id_image_path,
                    CONCAT(u.first_name, ' ', COALESCE(u.middle_name, ''), ' ', u.last_name) AS full_name
                FROM DocumentRequest dr
                JOIN DocumentType dt ON dr.document_type_id = dt.document_type_id
                JOIN Users u ON dr.user_id = u.user_id
                WHERE dr.document_request_id = :id
            ");
            $stmt->execute([':id' => $reqId]);
            $result = $stmt->fetch();

            if ($result) {
                $response['success']       = true;
                $response['request']       = $result;

                // Optional: log "view" action
                logAuditTrail($pdo, $current_admin_id, 'VIEW', 'DocumentRequest', $reqId, 'Viewed document request details.');
            } else {
                $response['message']       = 'Record not found.';
            }

        // ------------------------------------------------
        // (B) SEND EMAIL FOR SOFTCOPY & AUTOMATIC COMPLETE
        // ------------------------------------------------
        } elseif ($action === 'send_email') {

            // 1) Fetch requester's info
            $stmt = $pdo->prepare("
                SELECT 
                    dr.document_request_id,
                    dt.document_name,
                    u.email,
                    CONCAT(u.first_name, ' ', u.last_name) AS requester_name
                FROM DocumentRequest dr
                JOIN DocumentType dt ON dr.document_type_id = dt.document_type_id
                JOIN Users u ON dr.user_id = u.user_id
                WHERE dr.document_request_id = :id
            ");
            $stmt->execute([':id' => $reqId]);
            $result = $stmt->fetch();

            if ($result && !empty($result['email'])) {

                // 2) Send Email using PHPMailer
                $mail = new PHPMailer(true);
                try {
                    // (Optional) $mail->SMTPDebug = 2;
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    // Adjust credentials accordingly:
                    $mail->Username   = 'barangayhub2@gmail.com';
                    $mail->Password   = 'eisy hpjz rdnt bwrp';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('no-reply@yourdomain.com', 'Barangay Hub');
                    $mail->addAddress($result['email'], $result['requester_name']);

                    $mail->Subject = 'Your Document Request: ' . $result['document_name'];
                    $mail->Body    = "Hello {$result['requester_name']},\n\nYour request for '{$result['document_name']}' has been processed and is attached (if applicable). Thank you!";

                    if ($mail->send()) {
                        // 3) Upon successful email, automatically mark as complete
                        $updateStmt = $pdo->prepare("
                            UPDATE DocumentRequest 
                            SET status = 'Complete' 
                            WHERE document_request_id = :id
                        ");
                        $updateStmt->execute([':id' => $reqId]);

                        // Log to audit trail
                        logAuditTrail(
                            $pdo, 
                            $current_admin_id, 
                            'UPDATE', 
                            'DocumentRequest', 
                            $reqId, 
                            'Sent document via email and marked as complete.'
                        );

                        $response['success'] = true;
                        $response['message'] = 'Email sent and request marked as complete.';
                    } else {
                        $response['message'] = 'Unable to send email (no exception).';
                    }
                } catch (Exception $e) {
                    $response['message'] = 'Mailer Error: ' . $mail->ErrorInfo;
                }

            } else {
                $response['message'] = 'Request or email information not found.';
            }

        // -------------------------------------
        // (C) MARK DOCUMENT REQUEST AS COMPLETE
        // -------------------------------------
        } elseif ($action === 'complete') {

            // 1) Mark as complete (Hardcopy scenario)
            $stmt = $pdo->prepare("
                UPDATE DocumentRequest 
                SET status = 'Complete' 
                WHERE document_request_id = :id
            ");
            if ($stmt->execute([':id' => $reqId])) {
                // Log to audit trail
                logAuditTrail(
                    $pdo,
                    $current_admin_id,
                    'UPDATE',
                    'DocumentRequest',
                    $reqId,
                    'Manually marked document as complete (hardcopy).'
                );

                $response['success'] = true;
                $response['message'] = 'Document request marked as complete.';
            } else {
                $response['message'] = 'Unable to mark request as complete.';
            }

        // -----------------------------
        // (D) DELETE (WITH REMARKS)
        // -----------------------------
        } elseif ($action === 'delete') {
            // We expect a POST request with 'remarks'
            $remarks = $_POST['remarks'] ?? '';

            // 1) Fetch request info + user email
            $stmt = $pdo->prepare("
                SELECT 
                    dr.document_request_id,
                    dt.document_name,
                    u.email,
                    CONCAT(u.first_name, ' ', u.last_name) AS requester_name
                FROM DocumentRequest dr
                JOIN DocumentType dt ON dr.document_type_id = dt.document_type_id
                JOIN Users u ON dr.user_id = u.user_id
                WHERE dr.document_request_id = :id
            ");
            $stmt->execute([':id' => $reqId]);
            $requestInfo = $stmt->fetch();

            if (!$requestInfo) {
                $response['message'] = 'Request not found; cannot delete.';
                echo json_encode($response);
                exit;
            }

            // 2) Email user about the reason for deletion
            if (!empty($requestInfo['email'])) {
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    // Adjust credentials accordingly:
                    $mail->Username   = 'barangayhub2@gmail.com';
                    $mail->Password   = 'eisy hpjz rdnt bwrp';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('no-reply@yourdomain.com', 'Barangay Hub');
                    $mail->addAddress($requestInfo['email'], $requestInfo['requester_name']);

                    $mail->Subject = 'Document Request Not Processed';
                    $mail->Body    = "Hello {$requestInfo['requester_name']},\n\n"
                                   . "Your request for '{$requestInfo['document_name']}' has been declined/removed.\n\n"
                                   . "Reason/Remarks:\n{$remarks}\n\n"
                                   . "If you have any questions, please contact our office. Thank you.";

                    $mail->send(); // best-effort; no need to check if it fails here
                } catch (Exception $e) {
                    // If email fails, we still proceed with deletion
                }
            }

            // 3) Delete from DB
            $stmtDel = $pdo->prepare("DELETE FROM DocumentRequest WHERE document_request_id = :id");
            if ($stmtDel->execute([':id' => $reqId])) {
                // Log to audit trail
                logAuditTrail(
                    $pdo,
                    $current_admin_id,
                    'DELETE',
                    'DocumentRequest',
                    $reqId,
                    'Deleted document request with remarks: ' . $remarks
                );

                $response['success'] = true;
                $response['message'] = 'Document request deleted successfully.';
            } else {
                $response['message'] = 'Unable to delete document request.';
            }
        }

    } catch (Exception $ex) {
        $response['message'] = 'Server Error: ' . $ex->getMessage();
    }

    // Respond with JSON and stop
    echo json_encode($response);
    exit;
}

// ----------------------------------------------------
// 3. Only include header + HTML if no specific action
// ----------------------------------------------------
require_once "../pages/header.php";

// EXAMPLE: Hard-coded barangay_id=1 to filter requests


// 1) Fetch all "Pending" doc requests (FIFO => earliest date first)
$stmt = $pdo->prepare("
    SELECT 
        dr.document_request_id,
        dr.request_date,
        dr.status,
        dr.delivery_method,
        dt.document_name,
        CONCAT(u.first_name, ' ', u.last_name) AS requester_name
    FROM DocumentRequest dr
    JOIN DocumentType dt ON dr.document_type_id = dt.document_type_id
    JOIN Users u ON dr.user_id = u.user_id
    WHERE u.barangay_id = :bid
      AND LOWER(dr.status) = 'pending'
    ORDER BY dr.request_date ASC
");
$stmt->execute([':bid' =>$bid]);
$docRequests = $stmt->fetchAll();

// 2) Fetch all "Complete" doc requests (History), also FIFO => earliest date first
$stmtHist = $pdo->prepare("
    SELECT 
        dr.document_request_id,
        dr.request_date,
        dr.status,
        dr.delivery_method,
        dt.document_name,
        CONCAT(u.first_name, ' ', u.last_name) AS requester_name
    FROM DocumentRequest dr
    JOIN DocumentType dt ON dr.document_type_id = dt.document_type_id
    JOIN Users u ON dr.user_id = u.user_id
    WHERE u.barangay_id = :bid
      AND LOWER(dr.status) = 'complete'
    ORDER BY dr.request_date ASC
");
$stmtHist->execute([':bid' => $bid]);
$completedRequests = $stmtHist->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Document Requests</title>
  <!-- Tailwind CSS -->
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2/dist/tailwind.min.css" rel="stylesheet">
  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@10"></script>
  <style>
  </style>
</head>
<body class="bg-gray-100">
  <div class="container mx-auto p-4">

    <!-- Pending Requests Section -->
    <section id="docRequests" class="mb-10">
  <header class="mb-6">
    <h1 class="text-3xl font-bold text-blue-800">Pending Document Requests</h1>
  </header>

  <input type="text" id="pendingSearch" 
         class="p-2 border rounded mb-4" 
         placeholder="Search pending requests...">

  <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200" id="docRequestsTable">
        <thead class="bg-gray-50">
          <tr>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sortable">
              Requested By
            </th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sortable">
              Document Type
            </th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sortable">
              Request Date
            </th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sortable">
              Delivery Method
            </th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sortable">
              Status
            </th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
              Actions
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <?php if (!empty($docRequests)): ?>
            <?php foreach ($docRequests as $req): ?>
            <tr class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 text-sm text-gray-900 border-b">
                <?= htmlspecialchars($req['requester_name']) ?>
              </td>
              <td class="px-4 py-3 text-sm text-gray-900 border-b">
                <?= htmlspecialchars($req['document_name']) ?>
              </td>
              <td class="px-4 py-3 text-sm text-gray-900 border-b">
                <?= htmlspecialchars($req['request_date']) ?>
              </td>
              <td class="px-4 py-3 text-sm text-gray-900 border-b">
                <?= htmlspecialchars($req['delivery_method']) ?>
              </td>
              <td class="px-4 py-3 text-sm border-b">
                <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded">
                  <?= htmlspecialchars($req['status']) ?>
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-900 border-b">
                <div class="flex items-center space-x-2">
                  <button class="viewDocRequestBtn text-blue-600 hover:text-blue-900" data-id="<?= $req['document_request_id'] ?>">
                    View
                  </button>
                  <?php if (strtolower($req['delivery_method'])==='softcopy'): ?>
                  <button class="sendDocEmailBtn bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700" data-id="<?= $req['document_request_id'] ?>">
                    Send Email
                  </button>
                  <?php else: ?>
                  <button class="printDocRequestBtn bg-gray-600 text-white px-3 py-1 rounded hover:bg-gray-700" data-id="<?= $req['document_request_id'] ?>">
                    Print
                  </button>
                  <button class="completeDocRequestBtn bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700" data-id="<?= $req['document_request_id'] ?>">
                    Complete
                  </button>
                  <?php endif; ?>
                  <button class="deleteDocRequestBtn bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700" data-id="<?= $req['document_request_id'] ?>">
                    Delete
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="px-4 py-4 text-center text-gray-500">No pending document requests found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

    <!-- Completed (History) Section -->
    <section id="docRequestsHistory">
  <header class="mb-6">
    <h1 class="text-3xl font-bold text-green-800">Document Requests History (Completed)</h1>
  </header>

  <input type="text" id="completedSearch" 
         class="p-2 border rounded mb-4" 
         placeholder="Search completed requests...">

  <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200" id="docRequestsHistoryTable">
        <thead class="bg-gray-50">
          <tr>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sortable">
              Requested By
            </th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sortable">
              Document Type
            </th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sortable">
              Request Date
            </th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sortable">
              Delivery Method
            </th>
            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sortable">
              Status
            </th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <?php if (!empty($completedRequests)): ?>
            <?php foreach ($completedRequests as $req): ?>
            <tr class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 text-sm text-gray-900 border-b">
                <?= htmlspecialchars($req['requester_name']) ?>
              </td>
              <td class="px-4 py-3 text-sm text-gray-900 border-b">
                <?= htmlspecialchars($req['document_name']) ?>
              </td>
              <td class="px-4 py-3 text-sm text-gray-900 border-b">
                <?= htmlspecialchars($req['request_date']) ?>
              </td>
              <td class="px-4 py-3 text-sm text-gray-900 border-b">
                <?= htmlspecialchars($req['delivery_method']) ?>
              </td>
              <td class="px-4 py-3 text-sm border-b">
                <span class="px-3 py-1 bg-green-100 text-green-800 rounded">
                  <?= htmlspecialchars($req['status']) ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" class="px-4 py-4 text-center text-gray-500">No completed document requests found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
  </div>

  <!-- Inline JavaScript to handle doc request actions -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {

      // ======================================
      // Searching & Sorting: common function
      // ======================================
      function tableSearch(inputElem, tableElem) {
        inputElem.addEventListener('keyup', function() {
          const term = this.value.toLowerCase();
          tableElem.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
          });
        });
      }

      // Sorting function
      function sortTableByColumn(table, columnIndex) {
        const tBody = table.querySelector('tbody');
        const rows = Array.from(tBody.querySelectorAll('tr'));
        // Check if we have a current sort direction
        const currentHeader = table.querySelectorAll('th')[columnIndex];
        const isAsc = currentHeader.getAttribute('data-sort-dir') === 'asc';
        // Toggle
        currentHeader.setAttribute('data-sort-dir', isAsc ? 'desc' : 'asc');

        // Remove from other THs
        table.querySelectorAll('th').forEach((th, idx) => {
          if (idx !== columnIndex) {
            th.removeAttribute('data-sort-dir');
          }
        });

        const sortedRows = rows.sort((a, b) => {
          const aVal = a.children[columnIndex].innerText.toLowerCase();
          const bVal = b.children[columnIndex].innerText.toLowerCase();

          if (aVal < bVal) return isAsc ? -1 : 1;
          if (aVal > bVal) return isAsc ? 1 : -1;
          return 0;
        });

        sortedRows.forEach(row => tBody.appendChild(row));
      }

      // Attach searching
      const pendingSearch = document.getElementById('pendingSearch');
      const docRequestsTable = document.getElementById('docRequestsTable');
      tableSearch(pendingSearch, docRequestsTable);

      const completedSearch = document.getElementById('completedSearch');
      const docRequestsHistoryTable = document.getElementById('docRequestsHistoryTable');
      tableSearch(completedSearch, docRequestsHistoryTable);

      // Attach sorting
      docRequestsTable.querySelectorAll('thead th.sortable').forEach((th, idx) => {
        th.addEventListener('click', () => {
          sortTableByColumn(docRequestsTable, idx);
        });
      });
      docRequestsHistoryTable.querySelectorAll('thead th.sortable').forEach((th, idx) => {
        th.addEventListener('click', () => {
          sortTableByColumn(docRequestsHistoryTable, idx);
        });
      });

      // Helper: show loading spinner (SweetAlert2)
      function showLoading() {
        Swal.fire({
          title: 'Please wait...',
          text: 'Processing your request.',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });
      }
      // Helper: hide loading spinner
      function hideLoading() {
        Swal.close();
      }

      // Helper function to do a GET and parse JSON
      function fetchJSON(url) {
        showLoading();
        return fetch(url)
          .then(resp => {
            if (!resp.ok) {
              hideLoading();
              throw new Error('Network response was not OK');
            }
            return resp.json();
          })
          .finally(() => hideLoading());
      }

      // (1) VIEW Document Request (show all user details + ID image)
     
      // (2) PRINT Document Request (Hardcopy)
      document.querySelectorAll('.printDocRequestBtn').forEach(btn => {
        btn.addEventListener('click', function() {
          let requestId = this.getAttribute('data-id');
          // If you have a specific print page or method, adapt here:
          window.open(`doc_request.php?action=print_document_request&id=${requestId}`, '_blank');
        });
      });

      // (3) SEND EMAIL (Softcopy) => Confirm => Send => Auto-complete
      document.querySelectorAll('.sendDocEmailBtn').forEach(btn => {
        btn.addEventListener('click', function() {
          let requestId = this.getAttribute('data-id');

          Swal.fire({
            title: 'Send Email?',
            text: 'Are you sure you want to send the requested document via email? This will automatically mark it as Complete.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, send it',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              // Proceed with sending
              fetchJSON(`doc_request.php?action=send_email&id=${requestId}`)
                .then(data => {
                  if (data.success) {
                    Swal.fire('Success', data.message, 'success').then(() => {
                      location.reload();
                    });
                  } else {
                    Swal.fire('Error', data.message, 'error');
                  }
                })
                .catch(error => {
                  Swal.fire('Error', 'An error occurred: ' + error.message, 'error');
                });
            }
          });
        });
      });

      // (4) COMPLETE Document Request (Hardcopy)
      document.querySelectorAll('.completeDocRequestBtn').forEach(btn => {
        btn.addEventListener('click', function() {
          let requestId = this.getAttribute('data-id');
          Swal.fire({
            title: 'Mark as Complete?',
            text: 'This will mark the request as ready for pickup.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, complete it',
            cancelButtonText: 'Cancel'
          }).then((result) => {
            if (result.isConfirmed) {
              fetchJSON(`doc_request.php?action=complete&id=${requestId}`)
                .then(data => {
                  if (data.success) {
                    Swal.fire('Completed', data.message, 'success')
                      .then(() => location.reload());
                  } else {
                    Swal.fire('Error', data.message, 'error');
                  }
                })
                .catch(error => {
                  Swal.fire('Error', 'An error occurred: ' + error.message, 'error');
                });
            }
          });
        });
      });

      // (5) DELETE Document Request => Ask for remarks => Email => Delete
      document.querySelectorAll('.deleteDocRequestBtn').forEach(btn => {
        btn.addEventListener('click', function() {
          let requestId = this.getAttribute('data-id');

          // Ask user for remarks
          Swal.fire({
            title: 'Delete Document Request?',
            text: 'Please provide remarks/reason for not processing this request. It will be emailed to the user.',
            icon: 'warning',
            input: 'textarea',
            inputPlaceholder: 'Enter your remarks here...',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            cancelButtonText: 'Cancel',
            preConfirm: (remarks) => {
              if (!remarks) {
                Swal.showValidationMessage('Remarks are required to proceed.');
              }
              return remarks;
            }
          }).then((result) => {
            if (result.isConfirmed && result.value) {
              // Remarks from the input
              const userRemarks = result.value;
              showLoading();
              // We'll do a normal fetch with POST to pass remarks
              const formData = new FormData();
              formData.append('remarks', userRemarks);

              fetch(`doc_request.php?action=delete&id=${requestId}`, {
                method: 'POST',
                body: formData
              })
              .then(resp => {
                if (!resp.ok) {
                  hideLoading();
                  throw new Error('Network response was not OK');
                }
                return resp.json();
              })
              .then(data => {
                hideLoading();
                if (data.success) {
                  Swal.fire('Deleted', data.message, 'success')
                    .then(() => location.reload());
                } else {
                  Swal.fire('Error', data.message, 'error');
                }
              })
              .catch(error => {
                hideLoading();
                Swal.fire('Error', 'An error occurred: ' + error.message, 'error');
              });
            }
          });
        });
      });
    });
 // Toggle View Modal


    function toggleViewDocModal() {
  document.getElementById('viewDocModal').classList.toggle('hidden');
}

document.querySelectorAll('.viewDocRequestBtn').forEach(btn => {
  btn.addEventListener('click', function() {
    const requestId = this.dataset.id;
    fetch(`doc_request.php?action=view_doc_request&id=${requestId}`)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const r = data.request;
          // Populate modal fields
          document.getElementById('viewReqName').textContent = r.full_name;
          document.getElementById('viewDocType').textContent = r.document_name;
          document.getElementById('viewReqDate').textContent = r.request_date;
          document.getElementById('viewStatus').textContent = r.status;
          document.getElementById('viewDelivery').textContent = r.delivery_method;
          document.getElementById('viewRemarks').textContent = r.request_remarks || 'N/A';
          document.getElementById('viewReqEmail').textContent = r.email || 'N/A';
          document.getElementById('viewReqContact').textContent = r.contact_number || 'N/A';
          document.getElementById('viewReqBirth').textContent = r.birth_date || 'N/A';
          document.getElementById('viewReqGender').textContent = r.gender || 'N/A';
          document.getElementById('viewReqMarital').textContent = r.marital_status || 'N/A';
          document.getElementById('viewEmergencyName').textContent = r.emergency_contact_name || 'N/A';
          document.getElementById('viewEmergencyContact').textContent = r.emergency_contact_number || 'N/A';
          document.getElementById('viewEmergencyAddress').textContent = r.emergency_contact_address || 'N/A';
          
          // Handle ID Image
          const idImageContainer = document.getElementById('viewIdImage');
          idImageContainer.innerHTML = r.id_image_path 
            ? `<img src="${r.id_image_path}" class="max-w-[300px] h-auto border rounded" alt="ID Image">`
            : 'No ID image available';

          toggleViewDocModal();
        } else {
          Swal.fire('Error', data.message || 'Unable to load details.', 'error');
        }
      });
  });
});
  </script>


  <!-- View Document Request Modal -->
<div id="viewDocModal" tabindex="-1" 
     class="hidden fixed top-0 left-0 right-0 z-50 w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full">
  <div class="relative w-full max-w-2xl max-h-full mx-auto">
    <div class="relative bg-white rounded-lg shadow">
      <!-- Header -->
      <div class="flex items-start justify-between p-5 border-b rounded-t">
        <h3 class="text-xl font-semibold text-gray-900">Document Request Details</h3>
        <button type="button" onclick="toggleViewDocModal()"
                class="text-gray-400 hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ml-auto inline-flex justify-center items-center">
          <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 14">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 6 6m0 0 6 6M7 7l6-6M7 7l-6 6"/>
          </svg>
        </button>
      </div>
      <!-- Body -->
      <div class="p-6 space-y-4 overflow-y-auto max-h-[calc(100%-6rem)] text-sm text-gray-800">
        <div class="grid grid-cols-2 gap-4">
          <div><strong>Requested By:</strong> <span id="viewReqName">—</span></div>
          <div><strong>Document Type:</strong> <span id="viewDocType">—</span></div>
          <div><strong>Request Date:</strong> <span id="viewReqDate">—</span></div>
          <div><strong>Status:</strong> <span id="viewStatus">—</span></div>
          <div><strong>Delivery Method:</strong> <span id="viewDelivery">—</span></div>
          <div><strong>Remarks:</strong> <span id="viewRemarks">—</span></div>
        </div>
        
        <h4 class="text-lg font-medium pt-4 border-t">Requester Information</h4>
        <div class="grid grid-cols-2 gap-4">
          <div><strong>Email:</strong> <span id="viewReqEmail">—</span></div>
          <div><strong>Contact #:</strong> <span id="viewReqContact">—</span></div>
          <div><strong>Birth Date:</strong> <span id="viewReqBirth">—</span></div>
          <div><strong>Gender:</strong> <span id="viewReqGender">—</span></div>
          <div><strong>Marital Status:</strong> <span id="viewReqMarital">—</span></div>
        </div>

        <h4 class="text-lg font-medium pt-4 border-t">Emergency Contact</h4>
        <div class="grid grid-cols-2 gap-4">
          <div><strong>Name:</strong> <span id="viewEmergencyName">—</span></div>
          <div><strong>Contact #:</strong> <span id="viewEmergencyContact">—</span></div>
          <div><strong>Address:</strong> <span id="viewEmergencyAddress">—</span></div>
        </div>

        <h4 class="text-lg font-medium pt-4 border-t">ID Image</h4>
        <div id="viewIdImage" class="mt-2"></div>
      </div>
      <!-- Footer -->
      <div class="flex items-center justify-end p-5 border-t border-gray-200">
        <button type="button" onclick="toggleViewDocModal()"
                class="text-gray-500 bg-white hover:bg-gray-100 focus:ring-4 focus:outline-none focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 border border-gray-200">
          Close
        </button>
      </div>
    </div>
  </div>
</div>
</body>
</html>
