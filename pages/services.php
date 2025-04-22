<?php
session_start();
require_once "../config/dbconn.php";

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: ../pages/index.php");
  exit;
}

// Get user data from session
$userId    = $_SESSION['user_id'];
$userEmail = $_SESSION['user_email'] ?? '';
$userName  = $_SESSION['user_name']  ?? 'User';

// Get selected document type from query string if provided
$selectedDocumentType = isset($_GET['documentType']) 
    ? htmlspecialchars($_GET['documentType']) 
    : '';

// Fetch all barangays for the dropdown
try {
  $stmt = $pdo->prepare("SELECT barangay_id, barangay_name FROM Barangay ORDER BY barangay_name");
  $stmt->execute();
  $barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $error = "Database error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Barangay Hub - Document Request</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <link rel="stylesheet" href="../styles/services.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
  <header>
    <nav class="navbar">
      <a href="#" class="logo">
        <img src="../photo/logo.png" alt="Barangay Hub Logo" />
        <h2>Barangay Hub</h2>
      </a>
      <button class="mobile-menu-btn" aria-label="Toggle navigation menu">
        <i class="fas fa-bars"></i>
      </button>
      <div class="nav-links">
        <a href="../pages/user_dashboard.php#home">Home</a>
        <a href="../pages/user_dashboard.php#about">About</a>
        <a href="../pages/user_dashboard.php#services">Services</a>
        <a href="../pages/user_dashboard.php#contact">Contact</a>
        <a href="edit_account.php">Account</a>
        <a href="../functions/logout.php" style="color: red;">
          <i class="fas fa-sign-out-alt"></i> Logout
        </a>
      </div>
    </nav>
  </header>

  <main>
    <section class="wizard-section">
      <div class="wizard-container">
        <h2 class="form-header">Document Request</h2>
        <form id="documentRequestForm" class="wizard-form" enctype="multipart/form-data">
          
          <!-- ID Upload -->
          <div class="form-row upload-id">
            <label for="uploadId">Upload Your ID (Govt-issued ID or Birth Certificate)</label>
            <input type="file" name="uploadId" id="uploadId" accept="image/jpeg,image/png,application/pdf" required />
            <div id="idPreviewContainer">
              <img id="uploadIdPreview" src="" alt="ID Preview" style="max-width:200px; display:none;" />
            </div>
            <small>Max file size: 2MB. Formats: JPG, PNG, PDF</small>
          </div>

          <!-- Barangay Dropdown -->
          <div class="form-row">
            <label for="barangaySelect">Select Barangay</label>
            <select id="barangaySelect" name="barangay_id" required>
              <option value="">Select Barangay</option>
              <?php foreach($barangays as $b): ?>
                <option value="<?= htmlspecialchars($b['barangay_id']) ?>">
                  <?= htmlspecialchars($b['barangay_name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Document Type -->
          <div class="form-row">
            <label for="documentType">Select Document</label>
            <select id="documentType" name="documentType" required>
              <option value="">Select Document</option>
              <option value="barangayClearance"   <?= $selectedDocumentType==='barangayClearance'   ? 'selected' : '' ?>>Barangay Clearance</option>
              <option value="firstTimeJobSeeker"  <?= $selectedDocumentType==='firstTimeJobSeeker'  ? 'selected' : '' ?>>First Time Job Seeker</option>
              <option value="proofOfResidency"    <?= $selectedDocumentType==='proofOfResidency'    ? 'selected' : '' ?>>Proof of Residency</option>
              <option value="barangayIndigency"   <?= $selectedDocumentType==='barangayIndigency'   ? 'selected' : '' ?>>Barangay Indigency</option>
              <option value="goodMoralCertificate"<?= $selectedDocumentType==='goodMoralCertificate'? 'selected' : '' ?>>Good Moral Certificate</option>
              <option value="noIncomeCertification"<?= $selectedDocumentType==='noIncomeCertification'? 'selected' : '' ?>>No Income Certification</option>
            </select>
          </div>

                    <!-- Document-Specific Fields -->
          <div id="barangayClearanceFields" class="doc-fields" style="display:none;">
            <div class="form-row">
              <label for="purposeClearance">Purpose</label>
              <input type="text" id="purposeClearance" name="purposeClearance" placeholder="Enter purpose for Barangay Clearance">
            </div>
          </div>

          <div id="proofOfResidencyFields" class="doc-fields" style="display:none;">
            <div class="form-row">
              <label for="residencyDuration">Duration of Residency</label>
              <input type="text" id="residencyDuration" name="residencyDuration" placeholder="Enter residency duration">
            </div>
            <div class="form-row">
              <label for="residencyPurpose">Purpose</label>
              <input type="text" id="residencyPurpose" name="residencyPurpose" placeholder="Enter purpose">
            </div>
          </div>

          <div id="goodMoralCertificateFields" class="doc-fields" style="display:none;">
            <div class="form-row">
              <label for="gmcPurpose">Purpose</label>
              <input type="text" id="gmcPurpose" name="gmcPurpose" placeholder="Enter purpose">
            </div>
          </div>

          <div id="noIncomeCertificationFields" class="doc-fields" style="display:none;">
            <div class="form-row">
              <label for="nicReason">Reason for Request</label>
              <input type="text" id="nicReason" name="nicReason" placeholder="Enter reason">
            </div>
          </div>

          <div id="barangayIndigencyFields" class="doc-fields" style="display:none;">
            <div class="form-row">
              <label for="indigencyIncome">Monthly Income</label>
              <input type="text" id="indigencyIncome" name="indigencyIncome" placeholder="Enter monthly income">
            </div>
            <div class="form-row">
              <label for="indigencyReason">Reason for Indigency</label>
              <input type="text" id="indigencyReason" name="indigencyReason" placeholder="Enter reason">
            </div>
          </div>

          <!-- Delivery Method -->
          <div class="form-row">
            <label for="deliveryMethod">Delivery Method</label>
            <select id="deliveryMethod" name="deliveryMethod" required>
              <option value="">Select Delivery Method</option>
              <option value="Softcopy">Softcopy</option>
              <option value="Hardcopy">Hardcopy</option>
            </select>
          </div>

          <button type="submit" class="btn cta-button">Submit Request</button>
        </form>
      </div>
    </section>
  </main>

  <footer class="footer">
    <p>&copy; 2025 Barangay Hub. All rights reserved.</p>
  </footer>

  <script>
    const documentTypeSelect = document.getElementById('documentType');
    documentTypeSelect.addEventListener('change', () => {
      document.querySelectorAll('.doc-fields').forEach(el => el.style.display = 'none');
      const target = document.getElementById(documentTypeSelect.value + 'Fields');
      if (target) target.style.display = 'block';
    });

    // Preview ID upload
    document.getElementById("uploadId").addEventListener("change", (e) => {
      const file = e.target.files[0];
      if (file) {
        // Validate file size (2MB max)
        if (file.size > 2 * 1024 * 1024) {
          Swal.fire('Error', 'File size exceeds 2MB limit', 'error');
          e.target.value = ''; // Clear the input
          return;
        }
        
        // Validate file type
        const fileType = file.type;
        if (!['image/jpeg', 'image/png', 'application/pdf'].includes(fileType)) {
          Swal.fire('Error', 'Only JPG, PNG, and PDF files are allowed', 'error');
          e.target.value = ''; // Clear the input
          return;
        }
        
        // Only show preview for images
        if (fileType.startsWith('image/')) {
          const reader = new FileReader();
          reader.onload = ev => {
            const preview = document.getElementById("uploadIdPreview");
            preview.src = ev.target.result;
            preview.style.display = "block";
          };
          reader.readAsDataURL(file);
        } else {
          // Hide preview for non-image files
          document.getElementById("uploadIdPreview").style.display = "none";
        }
      }
    });

    // On page load, pre-select document type and show its fields
    document.addEventListener('DOMContentLoaded', () => {
      <?php if ($selectedDocumentType): ?>
        documentTypeSelect.value = "<?= $selectedDocumentType ?>";
        documentTypeSelect.dispatchEvent(new Event('change'));
      <?php endif; ?>
    });

    // Form submission
    document.getElementById('documentRequestForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      
      // Basic form validation
      const form = e.target;
      const docType = form.documentType.value;
      
      // Check if document-specific required fields are filled
      let missingFields = [];
      
      if (docType === 'barangayClearance' && !form.purposeClearance.value.trim()) {
        missingFields.push('Purpose for Barangay Clearance');
      }
      
      if (docType === 'proofOfResidency') {
        if (!form.residencyDuration.value.trim()) missingFields.push('Duration of Residency');
        if (!form.residencyPurpose.value.trim()) missingFields.push('Purpose for Residency');
      }
      
      if (docType === 'goodMoralCertificate' && !form.gmcPurpose.value.trim()) {
        missingFields.push('Purpose for Good Moral Certificate');
      }
      
      if (docType === 'noIncomeCertification' && !form.nicReason.value.trim()) {
        missingFields.push('Reason for No Income Certification');
      }
      
      if (docType === 'barangayIndigency') {
        if (!form.indigencyIncome.value.trim()) missingFields.push('Monthly Income');
        if (!form.indigencyReason.value.trim()) missingFields.push('Reason for Indigency');
      }
      
      if (missingFields.length > 0) {
        Swal.fire('Missing Information', 'Please fill in: ' + missingFields.join(', '), 'warning');
        return;
      }
      
      // Create FormData object
      const formData = new FormData(form);
      
      try {
        // Show loading indicator
        Swal.fire({
          title: 'Submitting...',
          text: 'Please wait while we process your request',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });
       const response = await fetch('../functions/services.php', {
        method: 'POST',
         credentials: 'same-origin',
         body: formData
        });
        
        const result = await response.json();
        console.log("server response →", result);

        if (result.success) {
          Swal.fire({
            icon: 'success',
            title: 'Success',
            html: `${result.message}<br>${result.processing_message}`
          }).then(() => {
            window.location.href = '../pages/user_dashboard.php';
          });
        } else {
          Swal.fire('Error', result.error || 'Failed to submit request', 'error');
          
        }
      } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'Failed to submit request. Please try again later.', 'error');
      }
    });
  </script>
</body>
</html>