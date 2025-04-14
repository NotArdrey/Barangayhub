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
        <!-- Single Form Encompassing All Steps -->
        <form id="wizardForm" action="../functions/services.php" method="POST" enctype="multipart/form-data">
          
          <!-- STEP 1: PERSONAL with ID Image Validation -->
          <div class="form-step active" id="step-1">
            <div class="upload-id">
              <label for="uploadId">
                Upload Your ID Image (Govt-issued ID or Birth Certificate)
              </label>
              <input type="file" name="uploadId" id="uploadId" accept="image/*" />
              <!-- Hidden input to store the validated ID image path -->
              <input type="hidden" id="idImagePath" name="idImagePath" value="">
              <div id="idPreviewContainer">
                <img id="uploadIdPreview" src="" alt="ID Preview" style="max-width:200px; display:none;" />
              </div>
            </div>
            
            <!-- Autofill and Reset Buttons -->
            <div class="form-row" style="display:flex; gap:10px; margin: 1em 0;">
              <button type="button" id="autofillBtn" class="btn cta-button">Autofill</button>
              <button type="button" id="resetBtn" class="btn cta-button">Reset</button>
            </div>

            <!-- Personal Information Fields -->
            <div class="form-row">
              <label for="firstName">First Name (Required)</label>
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
          </div>

          <!-- STEP 2: ADDRESS with Detailed Fields -->
          <div class="form-step" id="step-2">
            <h2>Address</h2>
            <div class="form-row">
              <label for="residencyType">Residency</label>
              <select id="residencyType" name="residencyType" required>
                <option value="">Select Residency</option>
                <option value="Home Owner">Home Owner</option>
                <option value="Renter">Renter</option>
                <option value="Boarder">Boarder</option>
                <option value="Living-In">Living-In</option>
              </select>
            </div>
            <div class="form-row">
              <label for="yearsInSanRafael">Years in San Rafael</label>
              <input type="text" id="yearsInSanRafael" name="yearsInSanRafael" placeholder="Enter number of years residing in San Rafael" required />
            </div>
            <div class="form-row">
              <label for="blockLot">Block/Lot</label>
              <input type="text" id="blockLot" name="blockLot" placeholder="Enter Block/Lot" required />
            </div>
            <div class="form-row">
              <label for="phase">Phase</label>
              <input type="text" id="phase" name="phase" placeholder="Enter Phase" required />
            </div>
            <div class="form-row">
              <label for="street">Street</label>
              <input type="text" id="street" name="street" placeholder="Enter Street" required />
            </div>
            <div class="form-row">
              <label for="subdivision">Subdivision</label>
              <input type="text" id="subdivision" name="subdivision" placeholder="Enter your subdivision" required />
            </div>
            <div class="form-row">
              <label for="barangay">Barangay</label>
              <select id="barangay" name="barangay" required>
                <option value="">Select Barangay</option>
                <!-- Example options -->
                <option value="1">BMA-Balagtas</option>
                <option value="2">Banca-Banca</option>
                <option value="3">Caingin</option>
                <!-- Add other options as needed -->
              </select>
            </div>
            <div class="form-row">
              <label for="city">City / Municipality</label>
              <input type="text" id="city" name="city" value="San Rafael" readonly />
            </div>
            <div class="form-row">
              <label for="province">Province</label>
              <input type="text" id="province" name="province" value="Bulacan" readonly />
            </div>
            <button type="button" class="btn cta-button prevBtn">Back</button>
            <button type="button" class="btn cta-button nextBtn">Next</button>
          </div>
          
          <!-- STEP 3: IMPORTANT INFORMATION with Document Type Selection -->
          <div class="form-step" id="step-3">
            <h2>Important Information</h2>
            <div class="form-row">
              <label for="documentType">Select Document Type</label>
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
            <!-- Document Specific Fields -->
            <div id="barangayClearanceFields" class="doc-fields" style="display:none;">
              <div class="form-row">
                <label for="purposeClearance">Purpose</label>
                <input type="text" id="purposeClearance" name="purposeClearance" placeholder="Enter purpose for Barangay Clearance">
              </div>
            </div>
            <div id="proofOfResidencyFields" class="doc-fields" style="display:none;">
              <div class="form-row">
                <label for="residencyDuration">Duration of Residency</label>
                <input type="text" id="residencyDuration" name="residencyDuration" placeholder="Enter duration of residency">
              </div>
              <div class="form-row">
                <label for="residencyPurpose">Purpose</label>
                <input type="text" id="residencyPurpose" name="residencyPurpose" placeholder="Enter purpose for Proof of Residency">
              </div>
            </div>
            <div id="goodMoralCertificateFields" class="doc-fields" style="display:none;">
              <div class="form-row">
                <label for="gmcPurpose">Purpose</label>
                <input type="text" id="gmcPurpose" name="gmcPurpose" placeholder="Enter purpose for Good Moral Certificate">
              </div>
            </div>
            <div id="noIncomeCertificationFields" class="doc-fields" style="display:none;">
              <div class="form-row">
                <label for="nicReason">Reason for Request</label>
                <input type="text" id="nicReason" name="nicReason" placeholder="Enter reason for No Income Certification">
              </div>
            </div>
            <div id="barangayIndigencyFields" class="doc-fields" style="display:none;">
              <div class="form-row">
                <label for="indigencyIncome">Monthly Income</label>
                <input type="text" id="indigencyIncome" name="indigencyIncome" placeholder="Enter Monthly Income">
              </div>
              <div class="form-row">
                <label for="indigencyReason">Reason for Indigency</label>
                <input type="text" id="indigencyReason" name="indigencyReason" placeholder="Enter reason for Barangay Indigency">
              </div>
            </div>
            <button type="button" class="btn cta-button prevBtn">Back</button>
            <button type="submit" class="btn cta-button">Submit</button>
          </div>

        </form>
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
        const target = document.getElementById(selected + 'Fields');
        if (target) target.style.display = 'block';
      }
    });
  </script>
  
  <!-- JavaScript for Autofill and Reset functionality -->
  <script>
    // Autofill from user data
    document.getElementById('autofillBtn').addEventListener('click', function() {
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
      document.getElementById("emergencyName").value    = userData.emergency_contact_name || '';
      document.getElementById("emergencyNumber").value  = userData.emergency_contact_number || '';
      document.getElementById("emergencyAddress").value = userData.emergency_contact_address || '';

      if (userData.id_image_path) {
        document.getElementById("idImagePath").value = userData.id_image_path;
        const idPreview = document.getElementById("uploadIdPreview");
        idPreview.src = userData.id_image_path;
        idPreview.style.display = "block";
      }
      
      alert("Fields have been autofilled based on your profile data.");
    });

    // Reset autofilled fields
    document.getElementById('resetBtn').addEventListener('click', function() {
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
      document.getElementById("emergencyName").value    = '';
      document.getElementById("emergencyNumber").value  = '';
      document.getElementById("emergencyAddress").value = '';
      document.getElementById("idImagePath").value = '';
      const idPreview = document.getElementById("uploadIdPreview");
      idPreview.src = "";
      idPreview.style.display = "none";
      
      alert("Personal, emergency fields and the validated ID reference have been cleared.");
    });
  </script>
  
  <!-- Additional Client-Side JS for Enhanced ID Processing, Validation, and Autofill -->
  <script>
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
        const dataURL = e.target.result;
        processImage(dataURL);
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

        if (isImageBlurry(canvas)) {
          Swal.close();
          alert("The image is too blurry. Please upload a clear image.");
          return;
        }

        const processedCanvas = preprocessImage(canvas);
        const processedDataURL = processedCanvas.toDataURL("image/png");

        Tesseract.recognize(processedDataURL, "eng", { logger: (m) => console.log(m) })
          .then((result) => {
            Swal.close();
            const ocrText = result.data.text;
            console.log("OCR Text:", ocrText);
            if (!verifyPHID(ocrText)) {
              alert("The uploaded document does not appear to be a valid PH government ID or Birth Certificate.");
              return;
            }
            const details = extractDetailsDynamic(ocrText);
            console.log("Extracted Details:", details);
            if (details["expiry"] && isExpired(details["expiry"])) {
              alert("The uploaded document is expired.");
              return;
            }
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

    function verifyPHID(text) {
      text = text.toLowerCase();
      console.log("Verifying text:", text);
      return text.includes("republic of the philippines") ||
             text.includes("government") ||
             text.includes("philippines") ||
             text.includes("birth certificate");
    }

    function extractDetailsDynamic(text) {
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

    function isExpired(expiryStr) {
      if (!expiryStr) return false;
      const parts = expiryStr.split("/");
      if (parts.length !== 3) return false;
      const [month, day, year] = parts.map((p) => parseInt(p, 10));
      return new Date(year, month - 1, day) < new Date();
    }

    function autofillForm(details) {
      console.log("Autofilling form with OCR details:", details);
      
      if (details["first name"] || details["name"]) {
        let fullName = details["first name"] || details["name"];
        let parts = fullName.split(" ");
        if (parts.length > 0) {
          document.getElementById("firstName").value = parts[0];
        }
        if (parts.length > 2) {
          document.getElementById("middleName").value = parts[1];
          document.getElementById("lastName").value = parts.slice(2).join(" ");
        } else if (parts.length === 2) {
          document.getElementById("lastName").value = parts[1];
        }
      }

      for (let key in details) {
        if (key.includes("dob") || key.includes("birth")) {
          let dobValue = details[key].trim();
          if (dobValue.indexOf("/") > -1) {
            let parts = dobValue.split("/");
            if (parts.length === 3) {
              dobValue = `${parts[2]}-${parts[0].padStart(2, "0")}-${parts[1].padStart(2, "0")}`;
            }
          }
          document.getElementById("birthday").value = dobValue;
          break;
        }
      }

      for (let key in details) {
        if (key.includes("gender")) {
          let genderVal = details[key].trim().toLowerCase();
          let genderField = document.getElementById("gender");
          for (let option of genderField.options) {
            if (option.value.toLowerCase() === genderVal) {
              genderField.value = option.value;
              break;
            }
          }
          break;
        }
      }

      for (let key in details) {
        if (key.includes("contact")) {
          document.getElementById("contactNumber").value = details[key].trim();
          break;
        }
      }

      alert("Newly uploaded ID has been validated and information auto-filled based on OCR data.");
    }
  </script>
</body>
</html>
