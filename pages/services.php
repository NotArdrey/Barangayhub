<?php
session_start();
require "../config/dbconn.php"; 

// Ensure user is logged in (i.e. is a document requester)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/index.php");
    exit;
}

// Fetch current user's data from the Users table to later use for autofilling using PDO
$user_id = $_SESSION['user_id'];
$userQuery = "SELECT * FROM Users WHERE user_id = ?";
$stmt = $pdo->prepare($userQuery);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Barangay Hub - Multi-Step Form</title>
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <!-- External CSS -->
  <link rel="stylesheet" href="../styles/services.css" />
  <!-- SweetAlert2 for notifications and loading animation -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
  <!-- Navigation (Unchanged) -->
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
        <a href="edit_account.php">Edit Account</a>
      </div>
    </nav>
  </header>

  <!-- Main Content -->
  <main>
    <!-- Multi-Step Form Section -->
    <section class="wizard-section">
      <div class="wizard-container">
        <!-- Step Indicators -->
        <ul class="wizard-steps"> 
          <li class="active">Personal</li>
          <li>Address</li>
          <li>Important Information</li>
        </ul>
        <!-- Form Steps -->
        <div class="wizard-form">
          <!-- STEP 1: PERSONAL with Upload ID Image -->
          <div class="form-step active" id="step-1">
            <form action="../function/services.php" method="POST" enctype="multipart/form-data">
              <div class="upload-id">
                <label for="uploadId">Upload Your ID Image (Govt-issued ID or Birth Certificate)</label>
                <input type="file" name="uploadId" id="uploadId" accept="image/*" required />
              </div>
              
              <div class="form-row" style="display:flex; gap:10px; margin: 1em 0;">
                <button type="button" id="autofillBtn" class="btn cta-button">Autofill</button>
                <button type="button" id="resetBtn" class="btn cta-button">Reset</button>
              </div>

              
              <div class="form-row">
                <label for="firstName">First Name (Required)</label>
                <!-- Field starts empty -->
                <input type="text" id="firstName" name="firstName" placeholder="Enter First Name" required>
              </div>
              <div class="form-row">
                <label for="middleName">Middle Name</label>
                <input type="text" id="middleName" name="middleName" placeholder="Enter Middle Name">
              </div>
              <div class="form-row">
                <label for="lastName">Last Name (Required)</label>
                <input type="text" id="lastName" name="lastName" placeholder="Enter Last Name" required>
              </div>
              <div class="form-row">
                <label for="birthday">Birthday</label>
                <input type="date" id="birthday" name="birthday" required>
              </div>
              <div class="form-row">
                <label for="gender">Gender</label>
                <select id="gender" name="gender" required>
                  <option value="">Select Gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Others">Others</option>
                </select>
              </div>
              <div class="form-row">
                <label for="contactNumber">Contact Number</label>
                <input type="text" id="contactNumber" name="contactNumber" placeholder="Enter Contact Number" required>
              </div>
              <div class="form-row">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter Email" required>
              </div>
              <div class="form-row">
                <label for="maritalStatus">Marital Status</label>
                <select id="maritalStatus" name="maritalStatus" required>
                  <option value="">Select Status</option>
                  <option value="Single">Single</option>
                  <option value="Married">Married</option>
                  <option value="Widowed">Widowed</option>
                  <option value="Separated">Separated</option>
                </select>
              </div>
              <div class="form-row">
                <label for="typeOfResidency">Type of Residency</label>
                <select id="typeOfResidency" name="typeOfResidency" required>
                  <option value="">Select Residency</option>
                  <option value="Living-In">Living-In</option>
                  <option value="Boarder">Boarder</option>
                  <option value="Owner">Owner</option>
                </select>
              </div>
              <div class="form-row">
                <label for="seniorOrPwd">Senior Citizen / PWD</label>
                <select id="seniorOrPwd" name="seniorOrPwd">
                  <option value="">None</option>
                  <option value="Senior Citizen">Senior Citizen</option>
                  <option value="PWD">PWD</option>
                </select>
              </div>
              <div class="form-row">
                <label for="soloParent">Solo Parent</label>
                <select id="soloParent" name="soloParent">
                  <option value="">No</option>
                  <option value="Yes">Yes</option>
                </select>
              </div>
              <h3>Emergency Details</h3>
              <div class="form-row">
                <label for="emergencyName">Emergency Contact Name</label>
                <input type="text" id="emergencyName" name="emergencyName" placeholder="Enter Name" required>
              </div>
              <div class="form-row">
                <label for="emergencyNumber">Emergency Contact Number</label>
                <input type="text" id="emergencyNumber" name="emergencyNumber" placeholder="Enter Number" required>
              </div>
              <div class="form-row">
                <label for="emergencyAddress">Emergency Contact Address</label>
                <input type="text" id="emergencyAddress" name="emergencyAddress" placeholder="Enter Address" required>
              </div>

              <!-- Next Step button -->
              <button type="button" class="btn cta-button nextBtn">Next</button>
              <!-- Hidden submit button (if needed for final submission) -->
              <button type="submit" class="btn cta-button" style="display:none;">Submit</button>
            </form>
          </div>

          <!-- STEP 2: ADDRESS with Detailed Fields -->
          <div class="form-step" id="step-2">
            <h2>Address</h2>
            <form>
              <div class="form-row">
                <label for="residencyType">Residency</label>
                <select id="residencyType" required>
                  <option value="">Select Residency</option>
                  <option value="Home Owner">Home Owner</option>
                  <option value="Renter">Renter</option>
                  <option value="Boarder">Boarder</option>
                  <option value="Living-In">Living-In</option>
                </select>
              </div>
              <div class="form-row">
                <label for="yearsInSanRafael">Years in San Rafael</label>
                <input type="text" id="yearsInSanRafael" placeholder="Enter number of years residing in San Rafael" required />
              </div>
              <div class="form-row">
                <label for="blockLot">Block/Lot</label>
                <input type="text" id="blockLot" placeholder="Enter Block/Lot" required />
              </div>
              <div class="form-row">
                <label for="phase">Phase</label>
                <input type="text" id="phase" placeholder="Enter Phase" required />
              </div>
              <div class="form-row">
                <label for="street">Street</label>
                <input type="text" id="street" placeholder="Enter Street" required />
              </div>
              <div class="form-row">
                <label for="subdivision">Subdivision</label>
                <input type="text" id="subdivision" placeholder="Enter your subdivision" required />
              </div>
              <div class="form-row">
                <label for="barangay">Barangay</label>
                <select id="barangay" name="barangay" required>
                  <option value="">Select Barangay</option>
                  <option value="1">BMA-Balagtas</option>
                  <option value="2">Banca-Banca</option>
                  <option value="3">Caingin</option>
                  <option value="4">Capihan</option>
                  <option value="5">Coral na Bato</option>
                  <option value="6">Cruz na Daan</option>
                  <option value="7">Dagat-Dagatan</option>
                  <option value="8">Diliman I</option>
                  <option value="9">Diliman II</option>
                  <option value="10">Libis</option>
                  <option value="11">Lico</option>
                  <option value="12">Maasim</option>
                  <option value="13">Mabalas-Balas</option>
                  <option value="14">Maguinao</option>
                  <option value="15">Maronquillo</option>
                  <option value="16">Paco</option>
                  <option value="17">Pansumaloc</option>
                  <option value="18">Pantubig</option>
                  <option value="19">Pasong Bangkal</option>
                  <option value="20">Pasong Callos</option>
                  <option value="21">Pasong Intsik</option>
                  <option value="22">Pinacpinacan</option>
                  <option value="23">Poblacion</option>
                  <option value="24">Pulo</option>
                  <option value="25">Pulong Bayabas</option>
                  <option value="26">Salapungan</option>
                  <option value="27">Sampaloc</option>
                  <option value="28">San Agustin</option>
                  <option value="29">San Roque</option>
                  <option value="30">Sapang Pahalang</option>
                  <option value="31">Talacsan</option>
                  <option value="32">Tambubong</option>
                  <option value="33">Tukod</option>
                  <option value="34">Ulingao</option>
                </select>
              </div>
              <div class="form-row">
                <label for="city">City / Municipality</label>
                <input type="text" id="city" value="San Rafael" readonly />
              </div>
              <div class="form-row">
                <label for="province">Province</label>
                <input type="text" id="province" value="Bulacan" readonly />
              </div>
              <button type="button" class="btn cta-button prevBtn">Back</button>
              <button type="button" class="btn cta-button nextBtn">Next</button>
            </form>
          </div>
          
          <!-- STEP 3: IMPORTANT INFORMATION with Document Type Selection -->
          <div class="form-step" id="step-3">
            <h2>Important Information</h2>
            <form id="importantInfoForm">
              <div class="form-row">
                <label for="documentType">Select Document Type</label>
                <select id="documentType" required>
                  <option value="">Select Document</option>
                  <option value="barangayClearance">Barangay Clearance</option>
                  <option value="firstTimeJobSeeker">First Time Job Seeker</option>
                  <option value="proofOfResidency">Proof of Residency</option>
                  <option value="barangayIndigency">Barangay Indigency</option>
                  <option value="goodMoralCertificate">Good Moral Certificate</option>
                  <option value="noIncomeCertification">No Income Certification</option>
                </select>
              </div>
              <!-- Document Specific Fields -->
              <div id="barangayClearanceFields" class="doc-fields" style="display:none;">
                <div class="form-row">
                  <label for="purposeClearance">Purpose</label>
                  <input type="text" id="purposeClearance" placeholder="Enter purpose for Barangay Clearance" required>
                </div>
              </div>
              <div id="proofOfResidencyFields" class="doc-fields" style="display:none;">
                <div class="form-row">
                  <label for="residencyDuration">Duration of Residency</label>
                  <input type="text" id="residencyDuration" placeholder="Enter duration of residency" required>
                </div>
                <div class="form-row">
                  <label for="residencyPurpose">Purpose</label>
                  <input type="text" id="residencyPurpose" placeholder="Enter purpose for Proof of Residency" required>
                </div>
              </div>
              <div id="goodMoralCertificateFields" class="doc-fields" style="display:none;">
                <div class="form-row">
                  <label for="gmcPurpose">Purpose</label>
                  <input type="text" id="gmcPurpose" placeholder="Enter purpose for Good Moral Certificate" required>
                </div>
              </div>
              <div id="noIncomeCertificationFields" class="doc-fields" style="display:none;">
                <div class="form-row">
                  <label for="nicReason">Reason for Request</label>
                  <input type="text" id="nicReason" placeholder="Enter reason for No Income Certification" required>
                </div>
              </div>
              <div id="barangayIndigencyFields" class="doc-fields" style="display:none;">
                <div class="form-row">
                  <label for="indigencyIncome">Monthly Income</label>
                  <input type="text" id="indigencyIncome" placeholder="Enter Monthly Income" required>
                </div>
                <div class="form-row">
                  <label for="indigencyReason">Reason for Indigency</label>
                  <input type="text" id="indigencyReason" placeholder="Enter reason for Barangay Indigency" required>
                </div>
              </div>
              <button type="button" class="btn cta-button prevBtn">Back</button>
              <button type="button" class="btn cta-button nextBtn">Next</button>
            </form>
          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- Footer (Unchanged) -->
  <footer class="footer">
    <p>&copy; 2025 Barangay Hub. All rights reserved.</p>
  </footer>

  <!-- Hidden User Data for Autofill -->
  <script>
    var userData = <?php echo json_encode($user); ?>;
  </script>

  <!-- External Libraries and Scripts -->
  <script async src="https://docs.opencv.org/3.4.0/opencv.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/tesseract.js@2.1.1/dist/tesseract.min.js"></script>
  <script src="../js/idProcessor.js"></script>
  
  <!-- JavaScript for Pre-filling, Wizard Steps, Mobile Navigation, and Document Type Handling -->
  <script>
    // Pre-fill document type based on URL parameter (if needed)
    document.addEventListener("DOMContentLoaded", function() {
      const urlParams = new URLSearchParams(window.location.search);
      const service = urlParams.get('service');
      if (service) {
        const documentTypeSelect = document.getElementById('documentType');
        documentTypeSelect.value = service;
        documentTypeSelect.dispatchEvent(new Event('change'));
      }
    });

    // Mobile menu toggle
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');
    mobileMenuBtn.addEventListener('click', () => {
      navLinks.classList.toggle('active');
    });

    // Wizard step handling
    const steps = document.querySelectorAll('.wizard-steps li');
    const formSteps = document.querySelectorAll('.form-step');
    const nextBtns = document.querySelectorAll('.nextBtn');
    const prevBtns = document.querySelectorAll('.prevBtn');
    let currentStep = 0;

    function updateFormSteps() {
      formSteps.forEach((formStep, index) => {
        formStep.classList.toggle('active', index === currentStep);
      });
      steps.forEach((step, index) => {
        step.classList.toggle('active', index === currentStep);
        step.classList.toggle('completed', index < currentStep);
      });
    }

    nextBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        if (currentStep < formSteps.length - 1) {
          currentStep++;
          updateFormSteps();
        }
      });
    });

    prevBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        if (currentStep > 0) {
          currentStep--;
          updateFormSteps();
        }
      });
    });
    
    // Document Type Change Handler to display document-specific fields
    const documentTypeSelect = document.getElementById('documentType');
    documentTypeSelect.addEventListener('change', function() {
      const selected = this.value;
      const docFields = document.querySelectorAll('.doc-fields');
      docFields.forEach(field => field.style.display = 'none');
      if (selected) {
        document.getElementById(selected + 'Fields').style.display = 'block';
      }
    });
  </script>
  
  <!-- New JavaScript for Autofill and Reset functionality -->
  <script>
    // Autofill button: populate personal and emergency fields based on userData
    document.getElementById('autofillBtn').addEventListener('click', function() {
      // Personal Information
      document.getElementById("firstName").value    = userData.first_name    || '';
      document.getElementById("middleName").value   = userData.middle_name   || '';
      document.getElementById("lastName").value     = userData.last_name     || '';
      document.getElementById("birthday").value     = userData.birth_date    || '';
      if(userData.gender) {
        document.getElementById("gender").value     = userData.gender;
      }
      document.getElementById("contactNumber").value = userData.contact_number || '';
      document.getElementById("email").value         = userData.email         || '';
      if(userData.marital_status) {
        document.getElementById("maritalStatus").value = userData.marital_status;
      }
      document.getElementById("typeOfResidency").value = userData.type_of_residency || '';
      if(userData.senior_or_pwd) {
        document.getElementById("seniorOrPwd").value = userData.senior_or_pwd;
      }
      if(userData.solo_parent) {
        document.getElementById("soloParent").value = userData.solo_parent;
      }
      
      // Emergency Details
      document.getElementById("emergencyName").value    = userData.emergency_contact_name || '';
      document.getElementById("emergencyNumber").value  = userData.emergency_contact_number || '';
      document.getElementById("emergencyAddress").value = userData.emergency_contact_address || '';
      
      alert("Fields have been autofilled based on your profile data.");
    });

    // Reset button: clear all personal and emergency fields
    document.getElementById('resetBtn').addEventListener('click', function() {
      // Personal Information
      document.getElementById("firstName").value    = '';
      document.getElementById("middleName").value   = '';
      document.getElementById("lastName").value     = '';
      document.getElementById("birthday").value     = '';
      document.getElementById("gender").value       = '';
      document.getElementById("contactNumber").value= '';
      document.getElementById("email").value        = '';
      document.getElementById("maritalStatus").value  = '';
      document.getElementById("typeOfResidency").value= '';
      document.getElementById("seniorOrPwd").value    = '';
      document.getElementById("soloParent").value     = '';
      
      // Emergency Details
      document.getElementById("emergencyName").value    = '';
      document.getElementById("emergencyNumber").value  = '';
      document.getElementById("emergencyAddress").value = '';
      
      alert("Personal and emergency fields have been cleared.");
    });
  </script>
  
  <!-- Additional Client-Side JS for Enhanced ID Processing, Validation, and Autofill (unchanged) -->
  <script>
    // Listen for file selection on the uploadId input to process the document image
    document.addEventListener("DOMContentLoaded", function () {
      const uploadInput = document.getElementById("uploadId");
      if (uploadInput) {
        uploadInput.addEventListener("change", handleImageUpload);
      }
    });

    function handleImageUpload(event) {
      const file = event.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = function (e) {
        processImage(e.target.result);
      };
      reader.readAsDataURL(file);
    }

    function processImage(dataURL) {
      const img = new Image();
      img.src = dataURL;
      img.onload = function () {
        Swal.fire({
          title: 'Processing image...',
          allowOutsideClick: false,
          didOpen: () => { Swal.showLoading(); }
        });
        const canvas = document.createElement("canvas");
        canvas.width = img.width;
        canvas.height = img.height;
        const ctx = canvas.getContext("2d");
        ctx.drawImage(img, 0, 0);

        // Check for blurriness; if too blurry, prompt for a clearer image.
        if (isImageBlurry(canvas)) {
          Swal.close();
          alert("The image is too blurry. Please upload a clear image.");
          return;
        }

        const processedCanvas = preprocessImage(canvas);
        const processedDataURL = processedCanvas.toDataURL("image/png");

        // Use Tesseract.js to extract text from the processed image
        Tesseract.recognize(processedDataURL, "eng", { logger: (m) => console.log(m) })
          .then((result) => {
            Swal.close();
            const ocrText = result.data.text;
            console.log("OCR Text:", ocrText);
            if (!verifyDocumentText(ocrText)) {
              alert("The uploaded document does not appear to be a valid government ID or Birth Certificate.");
              return;
            }
            const details = extractDetailsFromText(ocrText);
            autofillForm(details);
          })
          .catch((error) => {
            Swal.close();
            console.error("OCR Error:", error);
            alert("Error processing the document image.");
          });
      };
    }

    function preprocessImage(canvas) {
      try {
        let src = cv.imread(canvas);
        let gray = new cv.Mat();
        cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY, 0);
        let blurred = new cv.Mat();
        cv.GaussianBlur(gray, blurred, new cv.Size(5, 5), 0, 0, cv.BORDER_DEFAULT);
        let thresholded = new cv.Mat();
        cv.adaptiveThreshold(blurred, thresholded, 255, cv.ADAPTIVE_THRESH_GAUSSIAN_C, cv.THRESH_BINARY, 11, 2);
        let processedCanvas = document.createElement("canvas");
        processedCanvas.width = canvas.width;
        processedCanvas.height = canvas.height;
        cv.imshow(processedCanvas, thresholded);
        src.delete(); gray.delete(); blurred.delete(); thresholded.delete();
        return processedCanvas;
      } catch (err) {
        console.error("Error in preprocessing:", err);
        return canvas;
      }
    }

    function isImageBlurry(canvas) {
      try {
        const src = cv.imread(canvas);
        const gray = new cv.Mat();
        cv.cvtColor(src, gray, cv.COLOR_RGBA2GRAY, 0);
        const laplacian = new cv.Mat();
        cv.Laplacian(gray, laplacian, cv.CV_64F);
        const mean = new cv.Mat();
        const stddev = new cv.Mat();
        cv.meanStdDev(laplacian, mean, stddev);
        const variance = Math.pow(stddev.doubleAt(0, 0), 2);
        src.delete(); gray.delete(); laplacian.delete(); mean.delete(); stddev.delete();
        return variance < 100;
      } catch (err) {
        console.error("Error in blur detection:", err);
        return false;
      }
    }

    function verifyDocumentText(text) {
      text = text.toLowerCase();
      return text.includes("republic of the philippines") ||
             text.includes("government") ||
             text.includes("birth certificate");
    }

    function extractDetailsFromText(text) {
      const details = {};
      text.split("\n").forEach((line) => {
        if (line.includes(":")) {
          const parts = line.split(":");
          const key = parts[0].trim().toLowerCase();
          const value = parts.slice(1).join(":").trim();
          if (key && value) { details[key] = value; }
        }
      });
      return details;
    }

    function autofillForm(details) {
      console.log("Autofilling form with details:", details);
      
      // This function can still be used to populate fields based on OCR if needed.
      // However, the newly added Autofill button works solely based on your saved user profile.
      
      alert("ID verified, but autofill from OCR has been disabled. Please use the 'Autofill' button for your profile data.");
    }
  </script>
</body>
</html>
