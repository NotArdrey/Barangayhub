<?php
session_start();
require "../config/dbconn.php";

if (!isset($_GET['id'])) {
    header("Location: error.php");
    exit();
}

$docRequestId = (int)$_GET['id'];

$sql = "
    SELECT
        dr.request_date,
        dr.status,
        dr.remarks,
        dr.delivery_method,
        MAX(CASE WHEN a.attr_key = 'clearance_purpose'  THEN a.attr_value END) AS clearance_purpose,
        MAX(CASE WHEN a.attr_key = 'residency_duration' THEN a.attr_value END) AS residency_duration,
        MAX(CASE WHEN a.attr_key = 'residency_purpose'  THEN a.attr_value END) AS residency_purpose,
        MAX(CASE WHEN a.attr_key = 'gmc_purpose'        THEN a.attr_value END) AS gmc_purpose,
        MAX(CASE WHEN a.attr_key = 'nic_reason'         THEN a.attr_value END) AS nic_reason,
        MAX(CASE WHEN a.attr_key = 'indigency_income'   THEN a.attr_value END) AS indigency_income,
        MAX(CASE WHEN a.attr_key = 'indigency_reason'   THEN a.attr_value END) AS indigency_reason,
        dt.document_name,
        dt.document_description,
        u.first_name,
        u.middle_name,
        u.last_name,
        u.email
    FROM DocumentRequest dr
    JOIN DocumentType dt ON dr.document_type_id = dt.document_type_id
    JOIN Users u ON dr.user_id = u.user_id
    LEFT JOIN DocumentRequestAttribute a ON a.request_id = dr.document_request_id
    WHERE dr.document_request_id = :docRequestId
    GROUP BY dr.document_request_id;
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':docRequestId' => $docRequestId]);
$docRequest = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$docRequest) {
    header("Location: not_found.php");
    exit();
}

$validityPeriods = [
    'Barangay Clearance' => '+6 months',
    'Certificate of Residency' => '+6 months',
    'Certificate of Indigency' => '+3 months',
    'Good Moral Character Certificate' => '+6 months',
    'No Income Certificate' => '+1 month',
    'Business Permit' => '+1 year',
    'Solo Parent Certificate' => '+1 year'
];

$documentType = $docRequest['document_name'];
$validUntil = date('F j, Y', strtotime($validityPeriods[$documentType] ?? '+3 months'));

$middle = !empty($docRequest['middle_name']) ? $docRequest['middle_name'] . " " : "";
$requesterName = "{$docRequest['first_name']} $middle{$docRequest['last_name']}";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Document - <?= htmlspecialchars($docRequest['document_name']) ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Source+Serif+Pro:wght@400;600&display=swap');
        
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #7f8c8d;
        }

        body {
            font-family: 'Source Serif Pro', serif;
            line-height: 1.6;
            margin: 0;
            padding: 40px;
            color: var(--primary-color);
        }

        .letterhead {
            border-bottom: 3px double var(--primary-color);
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            text-align: center;
        }

        .letterhead h1 {
            font-size: 2.5rem;
            margin: 0 0 0.5rem;
            letter-spacing: 1.5px;
        }

        .document-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border: 1px solid #ddd;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .section-title {
            font-size: 1.2rem;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
            margin: 2rem 0 1.5rem;
            text-transform: uppercase;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .detail-item strong {
            display: block;
            color: var(--secondary-color);
            margin-bottom: 0.3rem;
        }

        .official-stamp {
            margin-top: 3rem;
            text-align: right;
        }

        .signature-line {
            display: inline-block;
            border-bottom: 2px solid var(--primary-color);
            width: 200px;
            margin-top: 40px;
        }

        .print-controls {
            text-align: center;
            margin-bottom: 2rem;
        }

        .print-button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s ease;
        }

        .print-button:hover {
            background: #34495e;
        }

        @media print {
            .print-controls {
                display: none;
            }
            .document-container {
                border: none;
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="document-container">
        <div class="print-controls">
            <button class="print-button" onclick="window.print()">Print Official Document</button>
        </div>

        <div class="letterhead">
            <h1>Office of the Barangay Chairman</h1>
            <p>123 Municipal Street, Barangay 123, Sample City, Philippines</p>
        </div>

        <div class="document-header">
            <h2><?= htmlspecialchars($docRequest['document_name']) ?></h2>
            <?php if ($docRequest['document_description']): ?>
                <p class="document-description"><?= htmlspecialchars($docRequest['document_description']) ?></p>
            <?php endif; ?>
        </div>

        <div class="section-title">Requester Information</div>
        <div class="detail-grid">
            <div class="detail-item">
                <strong>Full Name</strong>
                <?= htmlspecialchars($requesterName) ?>
            </div>
            <div class="detail-item">
                <strong>Request Date</strong>
                <?= date('F j, Y', strtotime($docRequest['request_date'])) ?>
            </div>
            <div class="detail-item">
                <strong>Contact Email</strong>
                <?= htmlspecialchars($docRequest['email']) ?>
            </div>
            <div class="detail-item">
                <strong>Delivery Method</strong>
                <?= htmlspecialchars(ucfirst($docRequest['delivery_method'])) ?>
            </div>
        </div>

        <div class="section-title">Document Details</div>
        <div class="detail-grid">
            <?php if ($docRequest['clearance_purpose']): ?>
            <div class="detail-item">
                <strong>Clearance Purpose</strong>
                <?= htmlspecialchars($docRequest['clearance_purpose']) ?>
            </div>
            <?php endif; ?>

            <?php if ($docRequest['residency_duration']): ?>
            <div class="detail-item">
                <strong>Residency Duration</strong>
                <?= htmlspecialchars($docRequest['residency_duration']) ?>
            </div>
            <?php endif; ?>

            <?php if ($docRequest['gmc_purpose']): ?>
            <div class="detail-item">
                <strong>Certificate Purpose</strong>
                <?= htmlspecialchars($docRequest['gmc_purpose']) ?>
            </div>
            <?php endif; ?>

            <?php if ($docRequest['indigency_income'] || $docRequest['indigency_reason']): ?>
            <div class="detail-item">
                <strong>Monthly Income</strong>
                <?= $docRequest['indigency_income'] ? htmlspecialchars($docRequest['indigency_income']) : 'N/A' ?>
            </div>
            <div class="detail-item">
                <strong>Indigency Reason</strong>
                <?= $docRequest['indigency_reason'] ? htmlspecialchars($docRequest['indigency_reason']) : 'N/A' ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($docRequest['remarks']): ?>
        <div class="section-title">Additional Remarks</div>
        <p><?= htmlspecialchars($docRequest['remarks']) ?></p>
        <?php endif; ?>

        <div class="official-stamp">
            <div class="signature-line"></div>
            <p>Authorized Signature</p>
            <div style="margin-top: 20px;">
                <em>Official Seal</em>
                <div style="width: 100px; height: 100px; border: 2px solid var(--primary-color); 
                     display: inline-block; margin-left: 1rem;"></div>
            </div>
        </div>

        <div style="margin-top: 3rem; font-size: 0.9em; color: var(--secondary-color);">
            <p>This document is electronically generated and requires an official signature and seal for validity.<br>
            Valid until: <?= $validUntil ?></p>
        </div>
    </div>
</body>
</html>