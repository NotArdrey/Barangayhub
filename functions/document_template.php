<?php
session_start();
require "../config/dbconn.php";

if (!isset($_GET['id'])) {
    header("Location: error.php");
    exit();
}

$docRequestId = (int)$_GET['id'];

// Main document query
$sql = "
    SELECT
        dr.request_date,
        dr.barangay_id,
        dt.document_name,
        u.user_id,
        u.first_name,
        u.middle_name,
        u.last_name,
        u.birth_date,
        b.barangay_name,
        MAX(CASE WHEN a.attr_key = 'residency_duration' THEN a.attr_value END) AS residency_duration,
        MAX(CASE WHEN a.attr_key = 'indigency_income' THEN a.attr_value END) AS indigency_income,
        MAX(CASE WHEN a.attr_key = 'ra_reference' THEN a.attr_value END) AS ra_reference
    FROM DocumentRequest dr
    JOIN DocumentType dt ON dr.document_type_id = dt.document_type_id
    JOIN Users u ON dr.user_id = u.user_id
    JOIN Barangay b ON dr.barangay_id = b.barangay_id
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

// Fetch officials
$officialsSql = "
    SELECT 
        u.first_name, 
        u.middle_name, 
        u.last_name, 
        r.role_name
    FROM Users u
    JOIN Role r ON u.role_id = r.role_id
    WHERE u.barangay_id = :barangayId
    AND r.role_name IN ('Barangay Captain', 'Barangay Secretary', 'Barangay Treasurer', 'Chief Officer', 'Barangay Councilor')
    ORDER BY FIELD(r.role_name,
        'Barangay Captain',
        'Barangay Secretary',
        'Barangay Treasurer',
        'Chief Officer',
        'Barangay Councilor'
    )
";

$stmtOfficials = $pdo->prepare($officialsSql);
$stmtOfficials->execute([':barangayId' => $docRequest['barangay_id']]);
$officials = $stmtOfficials->fetchAll(PDO::FETCH_ASSOC);

// Process officials data
$officialsGrouped = [
    'captain' => [],
    'councilors' => [],
    'secretary' => [],
    'treasurer' => [],
    'chief' => []
];

foreach ($officials as $official) {
    switch ($official['role_name']) {
        case 'Barangay Captain':
            $officialsGrouped['captain'] = $official;
            break;
        case 'Barangay Councilor':
            $officialsGrouped['councilors'][] = $official;
            break;
        case 'Barangay Secretary':
            $officialsGrouped['secretary'] = $official;
            break;
        case 'Barangay Treasurer':
            $officialsGrouped['treasurer'] = $official;
            break;
        case 'Chief Officer':
            $officialsGrouped['chief'] = $official;
            break;
    }
}

// Calculate age
$birthDate = new DateTime($docRequest['birth_date']);
$today = new DateTime();
$age = $today->diff($birthDate)->y;

// Format names
$middle = !empty($docRequest['middle_name']) ? $docRequest['middle_name'] . " " : "";
$requesterName = strtoupper("{$docRequest['first_name']} $middle{$docRequest['last_name']}");

// Format date
$requestDate = new DateTime($docRequest['request_date']);
$formattedDate = $requestDate->format('jS \d\a\y \o\f F, Y');

// Document-specific variables
$docType = $docRequest['document_name'];
$purpose = '';
$additionalContent = '';
$showIndigency = false;
$showRA = false;

switch ($docType) {
    case 'First Time Jobseeker Certification':
        $purpose = 'availing the benefits of Republic Act 11261 (First Time Jobseeker Act of 2019)';
        $showRA = true;
        break;
    case 'Certificate of Indigency':
        $purpose = 'FINANCIAL ASSISTANCE purposes only';
        $showIndigency = true;
        break;
    default: // General Certification
        $purpose = 'any legal intent and purposes';
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $docType ?> - <?= htmlspecialchars($docRequest['barangay_name']) ?></title>
    <style>
        body {
            font-family: 'Times New Roman', serif;
            line-height: 1.5;
            margin: 2cm;
        }
        .header {
            text-align: center;
            margin-bottom: 1.5cm;
        }
        .certificate-title {
            font-size: 24pt;
            margin-bottom: 0.5cm;
            text-decoration: underline;
        }
        .content {
            font-size: 12pt;
            text-align: justify;
            margin-bottom: 1.5cm;
        }
        .signatures {
            margin-top: 2cm;
        }
        .signature-block {
            margin: 1cm 0;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            width: 60%;
            margin: 10px 0;
        }
        .official-list {
            margin: 5px 0;
        }
        .footer-note {
            font-size: 10pt;
            text-align: center;
            margin-top: 1cm;
        }
        .uppercase {
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="header">
        <h3>REPUBLIC OF THE PHILIPPINES</h3>
        <h3>PROVINCE OF BULACAN</h3>
        <h3>MUNICIPALITY OF SAN RAFAEL</h3>
        <h3>BARANGAY CAINGIN</h3>
        <h4>OFFICE OF THE PUNONG BARANGAY</h4>
    </div>

    <div class="content">
        <h1 class="certificate-title"><?= $docType ?></h1>
        
        <p>To Whom It May Concern:</p>
        
        <p>This is to certify that <strong class="uppercase"><?= $requesterName ?></strong>, 
        <?= $age ?> years old<?= $docType === 'General Certification' ? ',' : '' ?> 
        <?php if(isset($docRequest['residency_duration'])): ?>
            has been a bonafide resident of <?= htmlspecialchars($docRequest['barangay_name']) ?> 
            for <?= htmlspecialchars($docRequest['residency_duration']) ?>,
        <?php endif; ?>
        and has never been accused nor charged of any misbehavior in this barangay.</p>

        <?php if($showIndigency): ?>
            <p>This further certifies that <?= $requesterName ?> is one of the INDIGENT FAMILIES 
            residing in our barangay with <?= $docRequest['indigency_income'] ?? 'no fixed income' ?> 
            as per our records.</p>
        <?php endif; ?>

        <?php if($showRA): ?>
            <p>This also certifies that <?= $requesterName ?> is a FIRST-TIME JOBSEEKER 
            from our barangay <?= $docRequest['ra_reference'] ?? 'availing benefits under RA 11261' ?>.</p>
        <?php endif; ?>

        <p>This certification is issued this <?= $formattedDate ?> for <?= $purpose ?>.</p>
    </div>

    <div class="signatures">
        <?php if(!empty($officialsGrouped['captain'])): ?>
            <div class="signature-block">
                <div class="signature-line"></div>
                <strong>PUNONG BARANGAY</strong><br>
                <?= strtoupper($officialsGrouped['captain']['first_name'] . ' ' . $officialsGrouped['captain']['last_name']) ?>
            </div>
        <?php endif; ?>

        <div class="signature-block">
            <strong>BARANGAY COUNCILORS:</strong>
            <div class="official-list">
                <?php foreach($officialsGrouped['councilors'] as $councilor): ?>
                    <?= strtoupper($councilor['first_name'] . ' ' . $councilor['last_name']) ?><br>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="signature-block" style="margin-top: 1.5cm;">
            <?php foreach(['secretary', 'treasurer', 'chief'] as $role): ?>
                <?php if(!empty($officialsGrouped[$role])): ?>
                    <div style="display: inline-block; width: 30%; margin-right: 2%;">
                        <div class="signature-line"></div>
                        <strong><?= strtoupper(str_replace('_', ' ', $role)) ?></strong><br>
                        <?= strtoupper($officialsGrouped[$role]['first_name'] . ' ' . $officialsGrouped[$role]['last_name']) ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="footer-note">
        <p>NOT VALID WITHOUT DRY SEAL</p>
        <p>Pagpapaunlad ng <?= htmlspecialchars($docRequest['barangay_name']) ?>...... Pagtulungan Natin!</p>
    </div>
</body>
</html>