<?php
session_start();
require "../config/dbconn.php"; 

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Barangay Hub - Document Request</title>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <!-- Existing CSS for UI -->
  <link rel="stylesheet" href="../styles/services.css" />
  <!-- SweetAlert2 for notifications -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
  <!-- Navigation -->
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
        <a href="../functions/logout.php" style="color: red;"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </nav>
  </header>

  <!-- Single-Card Form Section -->
  <main>
    <section class="wizard-section">
      <div class="wizard-container">
        <h2 class="form-header">Document Request</h2>
        <form id="documentRequestForm" action="../functions/services.php" method="POST" enctype="multipart/form-data" class="wizard-form">
          <!-- ID Upload Field -->
          <div class="form-row upload-id">
            <label for="uploadId">
              Upload Your ID (Govt-issued ID or Birth Certificate)
            </label>
            <input type="file" name="uploadId" id="uploadId" accept="image/*" required />
            <div id="idPreviewContainer">
              <img id="uploadIdPreview" src="" alt="ID Preview" style="max-width:200px; display:none;" />
            </div>
          </div>
          <!-- Document Type Selection -->
          <div class="form-row">
            <label for="documentType">Select Document</label>
            <select id="documentType" name="documentType" required>
              <option value="">Select Document</option>
              <option value="barangayClearance">Barangay Clearance</option>
              <option value="firstTimeJobSeeker">First Time Job Seeker</option>
              <option value="proofOfResidency">Proof of Residency</option>
              <option value="barangayIndigency">Barangay Indigency</option>
              <option value="goodMoralCertificate">Good Moral Certificate</option>
              <option value="noIncomeCertification">No Income Certification</option>
            </select>
          </div>
          <!-- Document-Specific Optional Fields -->
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

  <!-- Footer -->
  <footer class="footer">
    <p>&copy; 2025 Barangay Hub. All rights reserved.</p>
  </footer>

  <!-- JavaScript for Field Handling -->
  <script>
    // Document Type change handler: show/hide document-specific fields
    const documentTypeSelect = document.getElementById('documentType');
    documentTypeSelect.addEventListener('change', function() {
      const selected = this.value;
      const docFields = document.querySelectorAll('.doc-fields');
      docFields.forEach(field => field.style.display = 'none');
      if (selected) {
        const target = document.getElementById(selected + 'Fields');
        if (target) target.style.display = 'block';
      }
    });

    // Image preview for ID file upload
    document.getElementById("uploadId").addEventListener("change", function(event) {
      const file = event.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          const preview = document.getElementById("uploadIdPreview");
          preview.src = e.target.result;
          preview.style.display = "block";
        };
        reader.readAsDataURL(file);
      }
    });
  </script>
</body>
</html>
