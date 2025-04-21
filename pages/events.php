<?php
session_start();

// ── 1) Dependencies & DB connection ───────────────────────────────
require "../vendor/autoload.php";
require "../config/dbconn.php";   // defines $pdo

// ── 2) Auth guard ─────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] < 2) {
    header("Location: ../pages/index.php");
    exit;
}
$bid = $_SESSION['barangay_id'];
function logAuditTrail(PDO $pdo, int $adminId, string $action, string $table, int $recordId, string $desc = '') {
    $pdo->prepare("
        INSERT INTO AuditTrail
          (admin_user_id, action, table_name, record_id, description)
        VALUES (?, ?, ?, ?, ?)
    ")->execute([
        $adminId,
        $action,
        $table,
        $recordId,
        $desc
    ]);
}

function sendEventEmails(PDO $pdo, array $event, int $barangayId, string $type) {
    // Fetch all users in the barangay
    $stmt = $pdo->prepare("SELECT email FROM Users WHERE barangay_id = ?");
    $stmt->execute([$barangayId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) return;

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        // SMTP Configuration (Replace with your settings)
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com';    // SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'barangayhub2@gmail.com'; // SMTP username
        $mail->Password   = 'eisy hpjz rdnt bwrp';       // SMTP password
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('noreply@barangayhub.com', 'Barangay Hub');
        $mail->isHTML(false);

        foreach ($users as $user) {
            $mail->clearAddresses();
            $mail->addAddress($user['email']);

            $subject = ($type === 'new') ? "New Event: {$event['title']}" : "Event Postponed: {$event['title']}";
            $mail->Subject = $subject;

            $message = "Dear Resident,\n\n";
            $message .= ($type === 'new') ? "A new event has been scheduled:\n\n" : "The following event has been postponed:\n\n";
            $message .= "Title: {$event['title']}\n";
            $message .= "Description: {$event['description']}\n";
            $message .= "Start: " . date('M d, Y h:i A', strtotime($event['start_datetime'])) . "\n";
            $message .= "End: " . date('M d, Y h:i A', strtotime($event['end_datetime'])) . "\n";
            $message .= "Location: {$event['location']}\n";
            if (!empty($event['organizer'])) $message .= "Organizer: {$event['organizer']}\n";
            $message .= "\nThank you,\nBarangay Management";

            $mail->Body = $message;
            $mail->send();
        }
    } catch (Exception $e) {
        error_log("Mail Error: " . $e->getMessage());
    }
}
// ── 3) Handle form submissions ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $start_datetime = filter_input(INPUT_POST, 'start_datetime', FILTER_SANITIZE_STRING);
    $end_datetime = filter_input(INPUT_POST, 'end_datetime', FILTER_SANITIZE_STRING);
    $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
    $organizer = filter_input(INPUT_POST, 'organizer', FILTER_SANITIZE_STRING);
    $event_id = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT) ?: 0;

    // Common data for both insert/update
    $barangay_id = $_SESSION['barangay_id'];
    $created_by = $_SESSION['user_id'];

    try {
        if (isset($_POST['delete']) && $event_id) {
            // Handle event postponement
            $stmt = $pdo->prepare(
                "UPDATE events 
                 SET status = 'postponed'
                 WHERE event_id = ? AND barangay_id = ?"
            );
            $stmt->execute([$event_id, $barangay_id]);
            
            // Get updated event data
            $stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
            $stmt->execute([$event_id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Send notifications
            if ($event) {
                sendEventEmails($pdo, $event, $barangay_id, 'postponed');
                logAuditTrail($pdo, $_SESSION['user_id'], 'UPDATE', 'Events', $event_id, "Event postponed");
            }
            
            $_SESSION['message'] = "Event postponed successfully. Residents have been notified.";
        } else {
            // Validate required fields
            $errors = [];
            if (empty($title)) $errors[] = 'Title is required';
            if (empty($start_datetime)) $errors[] = 'Start date/time is required';
            if (empty($end_datetime)) $errors[] = 'End date/time is required';
            if (empty($location)) $errors[] = 'Location is required';
            
            // Validate datetime logic
            if (strtotime($end_datetime) < strtotime($start_datetime)) {
                $errors[] = 'End time must be after start time';
            }

            if (!empty($errors)) {
                $_SESSION['message'] = implode('<br>', $errors);
            } else {
                if ($event_id) {
                    // Update existing event
                    $stmt = $pdo->prepare(
                        "UPDATE Events SET
                            title = ?, description = ?, start_datetime = ?,
                            end_datetime = ?, location = ?, organizer = ?
                         WHERE event_id = ? AND barangay_id = ?"
                    );
                    $stmt->execute([
                        $title, $description, $start_datetime,
                        $end_datetime, $location, $organizer,
                        $event_id, $barangay_id
                    ]);
                    logAuditTrail($pdo, $_SESSION['user_id'], 'UPDATE', 'Events', $event_id, "Event updated");
                    $_SESSION['message'] = "Event updated successfully.";
                } else {
                    // Create new event
                    $stmt = $pdo->prepare(
                        "INSERT INTO Events (
                            title, description, start_datetime, end_datetime,
                            location, organizer, barangay_id, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([
                        $title, $description, $start_datetime,
                        $end_datetime, $location, $organizer,
                        $barangay_id, $created_by
                    ]);
                    $newId = $pdo->lastInsertId();
                    
                    // Get new event data
                    $stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
                    $stmt->execute([$newId]);
                    $event = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Send notifications
                    if ($event) {
                        sendEventEmails($pdo, $event, $barangay_id, 'new');
                        logAuditTrail($pdo, $_SESSION['user_id'], 'INSERT', 'Events', $newId, "Event created");
                    }
                    
                    $_SESSION['message'] = "Event created successfully. Residents have been notified.";
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        $_SESSION['message'] = "An error occurred while processing your request.";
    }

    // Redirect to prevent resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// ── 4) Load events for display ─────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT * FROM Events WHERE barangay_id = :bid ORDER BY start_datetime DESC"
);
$stmt->execute([':bid' => $bid]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>
    <main class="ml-0 lg:ml-64 p-4 md:p-8 space-y-6">
        <!-- Header and Button Section -->
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-4 md:mb-0">Event Management</h1>
            <button onclick="toggleModal()" class="w-full md:w-auto text-white bg-blue-600 hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5">
                + Add New Event
            </button>
        </div>

        <!-- Session Messages -->
        <?php if (!empty($_SESSION['message'])): ?>
        <div class="flex items-center p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50" role="alert">
            <svg class="flex-shrink-0 inline w-4 h-4 me-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5ZM9.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3ZM12 15H8a1 1 0 0 1 0-2h1v-3H8a1 1 0 0 1 0-2h2a1 1 0 0 1 1 1v4h1a1 1 0 0 1 0 2Z"/>
            </svg>
            <div><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
        </div>
        <?php endif; ?>

        <!-- Events Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Organizer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php $events = $events ?? []; ?>
                        <?php if (count($events) > 0): ?>
                            <?php foreach ($events as $event): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($event['title']) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="font-medium"><?= date('M d, Y', strtotime($event['start_datetime'])) ?></span>
                                        <span class="text-gray-600"><?= date('h:i A', strtotime($event['start_datetime'])) ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="font-medium"><?= date('M d, Y', strtotime($event['end_datetime'])) ?></span>
                                        <span class="text-gray-600"><?= date('h:i A', strtotime($event['end_datetime'])) ?></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($event['location']) ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($event['organizer'] ?? 'N/A') ?></td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <div class="flex items-center space-x-3"> <!-- Removed justify-end -->
                                        <button onclick="editEvent(<?= $event['event_id'] ?>)" 
                                                class="p-2 text-blue-600 hover:text-blue-900 rounded-lg hover:bg-blue-50">
                                            Edit
                                        </button>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="event_id" value="<?= $event['event_id'] ?>">
                                            <input type="hidden" name="delete" value="1">
                                            <button type="button" onclick="confirmDelete(this.form)" 
                                                    class="p-2 text-red-600 hover:text-red-900 rounded-lg hover:bg-red-50">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-4 py-4 text-center text-gray-500">No events found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Event Modal -->
        <div id="eventModal" tabindex="-1" class="hidden fixed top-0 left-0 right-0 z-50 w-full p-4 overflow-x-hidden overflow-y-auto md:inset-0 h-[calc(100%-1rem)] max-h-full">
            <div class="relative w-full max-w-2xl max-h-full mx-auto">
                <div class="relative bg-white rounded-lg shadow">
                    <div class="flex items-start justify-between p-5 border-b rounded-t">
                        <h3 class="text-xl font-semibold text-gray-900" id="modalTitle">New Event</h3>
                        <button onclick="toggleModal()" class="text-gray-400 hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm w-8 h-8 ml-auto inline-flex justify-center items-center">X</button>
                    </div>
                    <form method="POST" class="p-6 space-y-4">
                        <input type="hidden" name="event_id" id="eventId">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">Event Title <span class="text-red-500">*</span></label>
                                <input type="text" name="title" required class="..." placeholder="Community Meeting">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">Start <span class="text-red-500">*</span></label>
                                <input type="datetime-local" name="start_datetime" required class="...">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">End <span class="text-red-500">*</span></label>
                                <input type="datetime-local" name="end_datetime" required class="...">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">Location <span class="text-red-500">*</span></label>
                                <input type="text" name="location" required class="..." placeholder="Barangay Hall">
                            </div>
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-700">Organizer</label>
                                <input type="text" name="organizer" class="..." placeholder="Optional">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea name="description" rows="3" class="..." placeholder="Enter event details..."></textarea>
                        </div>
                        <div class="flex items-center justify-end pt-6 space-x-3 border-t border-gray-200">
                            <button type="submit" class="...">Save Event</button>
                            <button type="button" onclick="toggleModal()" class="...">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        function toggleModal(eventId = null) {
            const modal = document.getElementById('eventModal');
            modal.classList.toggle('hidden');
            if (eventId) {
                <?php foreach ($events as $e): ?>
                if (<?= $e['event_id'] ?> === eventId) {
                    document.getElementById('eventId').value = <?= $e['event_id'] ?>;
                    document.querySelector('[name="title"]').value = '<?= addslashes($e['title']) ?>';
                    document.querySelector('[name="start_datetime"]').value = '<?= str_replace(' ', 'T', $e['start_datetime']) ?>';
                    document.querySelector('[name="end_datetime"]').value = '<?= str_replace(' ', 'T', $e['end_datetime']) ?>';
                    document.querySelector('[name="location"]').value = '<?= addslashes($e['location']) ?>';
                    document.querySelector('[name="organizer"]').value = '<?= addslashes($e['organizer']) ?>';
                    document.querySelector('[name="description"]').value = '<?= addslashes($e['description']) ?>';
                    document.getElementById('modalTitle').textContent = 'Edit Event';
                }
                <?php endforeach; ?>
            } else {
                document.getElementById('eventId').value = '';
                document.querySelector('form').reset();
                document.getElementById('modalTitle').textContent = 'New Event';
            }
        }

        function editEvent(id) {
            toggleModal(id);
        }

        function confirmDelete(form) {
            Swal.fire({
                title: 'Postpone Event?',
                text: "This will notify residents about the postponement!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, Postpone',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
        }
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.2.0/flowbite.min.js"></script>
</body>
</html>
