
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
            <div class="upload-id">
              <label for="uploadId">Upload Your ID Image</label>
              <input type="file" id="uploadId" accept="image/*" required />
            </div>
            <form action="../function/services.php" method="POST">
              <div class="form-row">
                <label for="firstName">First Name (Required)</label>
                <input type="text" id="firstName" placeholder="Enter First Name" required />
              </div>
              <div class="form-row">
                <label for="middleName">Middle Name</label>
                <input type="text" id="middleName" placeholder="Enter Middle Name" />
              </div>
              <div class="form-row">
                <label for="lastName">Last Name</label>
                <input type="text" id="lastName" placeholder="Enter Last Name" required />
              </div>
              <div class="form-row">
                <label for="birthday">Birthday</label>
                <input type="date" id="birthday" required />
              </div>
              <div class="form-row">
                <label for="gender">Gender</label>
                <select id="gender" required>
                  <option value="">Select Gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Others">Others</option>
                </select>
              </div>
              <div class="form-row">
                <label for="contactNumber">Contact Number</label>
                <input type="text" id="contactNumber" placeholder="Enter Contact Number" required />
              </div>
              <div class="form-row">
                <label for="email">Email</label>
                <input type="email" id="email" placeholder="Enter Email" required />
              </div>
              <div class="form-row">
                <label for="maritalStatus">Marital Status</label>
                <select id="maritalStatus" required>
                  <option value="">Select Status</option>
                  <option value="Single">Single</option>
                  <option value="Married">Married</option>
                  <option value="Widowed">Widowed</option>
                  <option value="Separated">Separated</option>
                </select>
              </div>
              <div class="form-row">
                <label for="typeOfResidency">Type of Residency</label>
                <select id="typeOfResidency" required>
                  <option value="">Select Residency</option>
                  <option value="Living-In">Living-In</option>
                  <option value="Boarder">Boarder</option>
                  <option value="Owner">Owner</option>
                </select>
              </div>
              <div class="form-row">
                <label for="seniorOrPwd">Senior Citizen / PWD</label>
                <select id="seniorOrPwd">
                  <option value="">None</option>
                  <option value="Senior Citizen">Senior Citizen</option>
                  <option value="PWD">PWD</option>
                </select>
              </div>
              <div class="form-row">
                <label for="soloParent">Solo Parent</label>
                <select id="soloParent">
                  <option value="">No</option>
                  <option value="Yes">Yes</option>
                </select>
              </div>
              <h3>Emergency Details</h3>
              <div class="form-row">
                <label for="emergencyName">Emergency Contact Name</label>
                <input type="text" id="emergencyName" placeholder="Enter Name" required />
              </div>
              <div class="form-row">
                <label for="emergencyNumber">Emergency Contact Number</label>
                <input type="text" id="emergencyNumber" placeholder="Enter Number" required />
              </div>
              <div class="form-row">
                <label for="emergencyAddress">Emergency Contact Address</label>
                <input type="text" id="emergencyAddress" placeholder="Enter Address" required />
              </div>
              <button type="button" class="btn cta-button nextBtn">Next</button>
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

  <!-- External Libraries and Scripts -->
  <script async src="https://docs.opencv.org/3.4.0/opencv.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/tesseract.js@2.1.1/dist/tesseract.min.js"></script>
  <script src="../js/idProcessor.js"></script>
  
  <!-- JavaScript for Pre-filling, Wizard Steps, Mobile Navigation, and Document Type Handling -->
  <script>
    // Pre-fill document type based on URL parameter
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
      if(selected){
        document.getElementById(selected + 'Fields').style.display = 'block';
      }
    });
  </script>
</body>
</html>
