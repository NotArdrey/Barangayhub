<?php
//document_template.php
session_start();
require "../config/dbconn.php";

// Make sure we have an ID in the query string
if (!isset($_GET['id'])) {
    die("No document request ID specified.");
}

$docRequestId = (int) $_GET['id'];

// 1. Fetch the request (DocumentRequest + DocumentType + Users)
$sql = "
    SELECT 
        dr.document_request_id,
        dr.request_date,
        dr.status,
        dr.remarks,
        dr.clearance_purpose,
        dr.residency_duration,
        dr.residency_purpose,
        dr.gmc_purpose,
        dr.nic_reason,
        dr.indigency_income,
        dr.indigency_reason,
        dr.delivery_method,
        
        dt.document_name,
        dt.document_description,
        
        u.first_name,
        u.middle_name,
        u.last_name,
        u.email
    FROM DocumentRequest dr
    JOIN DocumentType dt ON dr.document_type_id = dt.document_type_id
    JOIN Users u ON dr.user_id = u.user_id
    WHERE dr.document_request_id = :docRequestId
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':docRequestId' => $docRequestId]);
$docRequest = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$docRequest) {
    die("Document request not found.");
}

// 2. Build the Requester Full Name
$middle = !empty($docRequest['middle_name']) ? $docRequest['middle_name'] . " " : "";
$requesterName = $docRequest['first_name'] . " " . $middle . $docRequest['last_name'];

// 3. Display
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <title>Document Template - <?php echo htmlspecialchars($docRequest['document_name']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 2rem;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .doc-header h1 {
            margin-bottom: 0.2rem;
        }
        .doc-header p {
            margin: 0 0 1rem 0;
            color: #666;
        }
        .doc-section {
            margin-bottom: 1.5rem;
        }
        .doc-section p {
            margin: 0.3rem 0;
        }
        .field-label {
            font-weight: bold;
        }
        hr {
            margin: 2rem 0 1rem;
        }
        .print-button {
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
<div class="container">

    <!-- Print Button -->
    <div class="print-button">
        <button onclick="window.print()">Print this Document</button>
    </div>

    <!-- Document Header -->
    <div class="doc-header">
        <h1><?php echo htmlspecialchars($docRequest['document_name']); ?></h1>
        <?php if (!empty($docRequest['document_description'])): ?>
            <p><?php echo htmlspecialchars($docRequest['document_description']); ?></p>
        <?php endif; ?>
    </div>

    <!-- Document Details -->
    <div class="doc-section">
        <p>
            <span class="field-label">Requested By:</span>
            <?php echo htmlspecialchars($requesterName); ?>
        </p>
        <p>
            <span class="field-label">Email:</span>
            <?php echo htmlspecialchars($docRequest['email']); ?>
        </p>
        <p>
            <span class="field-label">Request Date:</span>
            <?php echo htmlspecialchars($docRequest['request_date']); ?>
        </p>
        <p>
            <span class="field-label">Status:</span>
            <?php echo htmlspecialchars($docRequest['status']); ?>
        </p>
        <p>
            <span class="field-label">Delivery Method:</span>
            <?php echo htmlspecialchars($docRequest['delivery_method']); ?>
        </p>
    </div>

    <hr>

    <!-- Special Fields Depending on Document Type or Non-empty Values -->
    <div class="doc-section">

        <?php if (!empty($docRequest['remarks'])): ?>
            <p><span class="field-label">Remarks:</span> <?php echo htmlspecialchars($docRequest['remarks']); ?></p>
        <?php endif; ?>

        <?php if (!empty($docRequest['clearance_purpose'])): ?>
            <p><span class="field-label">Clearance Purpose:</span> 
                <?php echo htmlspecialchars($docRequest['clearance_purpose']); ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($docRequest['residency_duration'])): ?>
            <p><span class="field-label">Residency Duration:</span> 
                <?php echo htmlspecialchars($docRequest['residency_duration']); ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($docRequest['residency_purpose'])): ?>
            <p><span class="field-label">Residency Purpose:</span> 
                <?php echo htmlspecialchars($docRequest['residency_purpose']); ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($docRequest['gmc_purpose'])): ?>
            <p><span class="field-label">Good Moral Purpose:</span> 
                <?php echo htmlspecialchars($docRequest['gmc_purpose']); ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($docRequest['nic_reason'])): ?>
            <p><span class="field-label">No-Income Reason:</span> 
                <?php echo htmlspecialchars($docRequest['nic_reason']); ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($docRequest['indigency_income']) || !empty($docRequest['indigency_reason'])): ?>
            <p><span class="field-label">Indigency Income:</span> 
                <?php echo !empty($docRequest['indigency_income'])
                    ? htmlspecialchars($docRequest['indigency_income'])
                    : 'N/A'; ?>
            </p>
            <p><span class="field-label">Indigency Reason:</span> 
                <?php echo !empty($docRequest['indigency_reason'])
                    ? htmlspecialchars($docRequest['indigency_reason'])
                    : 'N/A'; ?>
            </p>
        <?php endif; ?>

    </div>

    <hr>

    <!-- Sample Text or Additional Instructions -->
    <div class="doc-section">
        <p>This document template can be customized further to fit official formatting,  
        signatures, or seals for the requested document. You can incorporate official  
        letterheads, QR codes, or reference numbers as needed.</p>
    </div>

</div> <!-- /container -->
</body>
</html>
