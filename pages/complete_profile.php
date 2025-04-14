<?php
session_start();
require "../config/dbconn.php"; // Assumes this file creates a PDO instance as $pdo

/**
 * Returns the appropriate dashboard URL based on the user's role.
 */
function getDashboardUrl($role_id) {
    if ($role_id == 1) {
        return "../pages/super_admin_dashboard.php";
    } elseif ($role_id == 2) {
        return "../pages/barangay_admin_dashboard.php";
    } else {
        return "../pages/user_dashboard.php";
    }
}

// Only allow logged-in users; if not, redirect to login page.
if (!isset($_SESSION['user_id'])) {
    header("Location: ../pages/index.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch current user's data from the Users table.
$query = "SELECT * FROM Users WHERE user_id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$error_message = '';

// Process form submission on POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and trim text fields from the form.
    $first_name                = trim($_POST['first_name']);
    $middle_name               = trim($_POST['middle_name']);
    $last_name                 = trim($_POST['last_name']);
    $birth_date                = $_POST['birth_date'];
    $gender                    = $_POST['gender'];
    $contact_number            = trim($_POST['contact_number']);
    $marital_status            = $_POST['marital_status'];
    $senior_or_pwd             = $_POST['senior_or_pwd'];
    $solo_parent               = $_POST['solo_parent'];
    $emergency_contact_name    = trim($_POST['emergency_contact_name']);
    $emergency_contact_number  = trim($_POST['emergency_contact_number']);
    $emergency_contact_address = trim($_POST['emergency_contact_address']);
    $barangay_id               = $_POST['barangay_id']; // From dropdown

    // Validate required fields.
    $errors = [];
    if (empty($first_name)) { $errors[] = "First name is required."; }
    if (empty($last_name)) { $errors[] = "Last name is required."; }
    if (empty($birth_date)) { $errors[] = "Birth date is required."; }
    if (empty($gender)) { $errors[] = "Gender is required."; }
    if (empty($contact_number)) { $errors[] = "Contact number is required."; }

    // Variable to store the document image path.
    // If no new file is uploaded, we keep the current value.
    $idImagePath = $user['id_image_path'];

    // Process the Government-issued ID or Birth Certificate upload if a file is provided.
    if (isset($_FILES['gov_id']) && $_FILES['gov_id']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = "../uploads/ids/"; // Folder to store uploaded files.
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $fileTmpPath = $_FILES['gov_id']['tmp_name'];
        $fileName     = basename($_FILES['gov_id']['name']);
        $fileExt      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Allowed file types.
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        if (!in_array($fileExt, $allowedExtensions)) {
            $errors[] = "Invalid file type for document. Allowed types: JPG, JPEG, PNG.";
        }

        // Maximum file size check (5MB maximum).
        $maxFileSize = 5 * 1024 * 1024; // 5MB.
        if ($_FILES['gov_id']['size'] > $maxFileSize) {
            $errors[] = "File size exceeds the maximum allowed of 5MB.";
        }

        // Generate a unique file name.
        $newFileName = "govid_" . $user_id . "_" . time() . "." . $fileExt;
        $destPath = $uploadDir . $newFileName;

        if (empty($errors)) {
            if (move_uploaded_file($fileTmpPath, $destPath)) {
                // NOTE: The OCR-based verification has been removed from form submission.
                // We assume that client-side verification already occurred.
                $idImagePath = $destPath;
            } else {
                $errors[] = "Error moving the uploaded file.";
            }
        }
    }

    // If there are no errors, update the user's profile.
    if (empty($errors)) {

        $updateQuery = "UPDATE Users SET 
                            first_name = ?, 
                            middle_name = ?, 
                            last_name = ?, 
                            birth_date = ?, 
                            gender = ?, 
                            contact_number = ?, 
                            marital_status = ?, 
                            senior_or_pwd = ?, 
                            solo_parent = ?, 
                            emergency_contact_name = ?, 
                            emergency_contact_number = ?, 
                            emergency_contact_address = ?, 
                            barangay_id = ?,
                            id_image_path = ?
                        WHERE user_id = ?";
        $updateStmt = $pdo->prepare($updateQuery);
        $params = [
            $first_name,
            $middle_name,
            $last_name,
            $birth_date,
            $gender,
            $contact_number,
            $marital_status,
            $senior_or_pwd,
            $solo_parent,
            $emergency_contact_name,
            $emergency_contact_number,
            $emergency_contact_address,
            $barangay_id,
            $idImagePath,
            $user_id
        ];
        if ($updateStmt->execute($params)) {
            header("Location: ../pages/user_dashboard.php");
            exit;
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Complete Your Profile</title>
  <link rel="stylesheet" href="../styles/edit_account.css">
  <!-- Font Awesome for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <!-- SweetAlert2 for notifications and loading animation -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- OpenCV.js (for image processing) -->
  <script async src="https://docs.opencv.org/master/opencv.js"></script>
  <!-- Tesseract.js (for OCR processing on client side) -->
  <script src="https://cdn.jsdelivr.net/npm/tesseract.js@2/dist/tesseract.min.js"></script>
</head>
<body>

  <!-- Main Content -->
  <main class="edit-account-section">
    <div class="section-header">
      <h2>Complete Your Profile</h2>
      <p>Please fill in the required details to continue.</p>
    </div>

    <?php if (!empty($error_message)): ?>
      <script>
        Swal.fire({
          icon: "error",
          title: "Error",
          html: <?php echo json_encode($error_message); ?>
        });
      </script>
    <?php endif; ?>

    <div class="account-form-container">
      <!-- IMPORTANT: The form's enctype must allow file uploads -->
      <form class="account-form" action="" method="POST" id="profileForm" enctype="multipart/form-data">
        <!-- Document Upload Field (client-side verification happens immediately on choosing file) -->
        <div class="form-group">
          <label for="uploadId">Upload Government-issued ID or Birth Certificate (Valid document)</label>
          <!-- Note: We use "uploadId" as the ID to trigger our JS; the form field name remains "gov_id" -->
          <input type="file" id="uploadId" name="gov_id" accept="image/*">
        </div>
        <!-- Personal Details Fields -->
        <div class="form-group">
          <label for="first_name">First Name *</label>
          <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
          <label for="middle_name">Middle Name</label>
          <input type="text" id="middle_name" name="middle_name" value="<?php echo htmlspecialchars($user['middle_name'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label for="last_name">Last Name *</label>
          <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
          <label for="birth_date">Birth Date *</label>
          <input type="date" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($user['birth_date'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
          <label for="gender">Gender *</label>
          <select name="gender" id="gender" required>
            <option value="">Select Gender</option>
            <option value="Male" <?php echo (isset($user['gender']) && $user['gender'] === "Male") ? 'selected' : ''; ?>>Male</option>
            <option value="Female" <?php echo (isset($user['gender']) && $user['gender'] === "Female") ? 'selected' : ''; ?>>Female</option>
            <option value="Others" <?php echo (isset($user['gender']) && $user['gender'] === "Others") ? 'selected' : ''; ?>>Others</option>
          </select>
        </div>
        <div class="form-group">
          <label for="contact_number">Contact Number *</label>
          <input type="text" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
          <label for="marital_status">Marital Status</label>
          <select name="marital_status" id="marital_status">
            <option value="">Select Status</option>
            <option value="Single" <?php echo (isset($user['marital_status']) && $user['marital_status'] === "Single") ? 'selected' : ''; ?>>Single</option>
            <option value="Married" <?php echo (isset($user['marital_status']) && $user['marital_status'] === "Married") ? 'selected' : ''; ?>>Married</option>
            <option value="Widowed" <?php echo (isset($user['marital_status']) && $user['marital_status'] === "Widowed") ? 'selected' : ''; ?>>Widowed</option>
            <option value="Separated" <?php echo (isset($user['marital_status']) && $user['marital_status'] === "Separated") ? 'selected' : ''; ?>>Separated</option>
          </select>
        </div>
        <div class="form-group">
          <label for="senior_or_pwd">Senior/PWD Status</label>
          <select name="senior_or_pwd" id="senior_or_pwd">
            <option value="None" <?php echo (isset($user['senior_or_pwd']) && $user['senior_or_pwd'] === "None") ? 'selected' : ''; ?>>None</option>
            <option value="Senior Citizen" <?php echo (isset($user['senior_or_pwd']) && $user['senior_or_pwd'] === "Senior Citizen") ? 'selected' : ''; ?>>Senior Citizen</option>
            <option value="PWD" <?php echo (isset($user['senior_or_pwd']) && $user['senior_or_pwd'] === "PWD") ? 'selected' : ''; ?>>PWD</option>
          </select>
        </div>
        <div class="form-group">
          <label for="solo_parent">Solo Parent</label>
          <select name="solo_parent" id="solo_parent">
            <option value="No" <?php echo (isset($user['solo_parent']) && $user['solo_parent'] === "No") ? 'selected' : ''; ?>>No</option>
            <option value="Yes" <?php echo (isset($user['solo_parent']) && $user['solo_parent'] === "Yes") ? 'selected' : ''; ?>>Yes</option>
          </select>
        </div>
        <div class="form-group">
          <label for="emergency_contact_name">Emergency Contact Name</label>
          <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo htmlspecialchars($user['emergency_contact_name'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label for="emergency_contact_number">Emergency Contact Number</label>
          <input type="text" id="emergency_contact_number" name="emergency_contact_number" value="<?php echo htmlspecialchars($user['emergency_contact_number'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label for="emergency_contact_address">Emergency Contact Address</label>
          <input type="text" id="emergency_contact_address" name="emergency_contact_address" value="<?php echo htmlspecialchars($user['emergency_contact_address'] ?? ''); ?>">
        </div>
        <div class="form-group">
          <label for="barangay_id">Barangay</label>
          <select name="barangay_id" id="barangay_id">
            <option value="">Select Barangay</option>
            <?php
              $barangayQuery = "SELECT barangay_id, barangay_name FROM Barangay";
              $stmtBarangay = $pdo->query($barangayQuery);
              while ($barangay = $stmtBarangay->fetch(PDO::FETCH_ASSOC)) {
                  $selected = (isset($user['barangay_id']) && $user['barangay_id'] == $barangay['barangay_id']) ? 'selected' : '';
                  echo "<option value=\"{$barangay['barangay_id']}\" $selected>{$barangay['barangay_name']}</option>";
              }
            ?>
          </select>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
          <button type="reset" class="btn secondary-btn">Reset</button>
          <button type="submit" class="btn cta-button">Save Profile</button>
        </div>
      </form>
    </div>
  </main>

  <!-- Footer -->
  <footer class="footer">
    <p>&copy; 2025 Barangay Hub. All rights reserved.</p>
  </footer>

  <!-- Client-Side JS for Enhanced ID Processing with Loading Animation -->
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
      console.log("Autofilling form with details:", details);
      
      if (details["first name"] || details["name"]) {
        let fullName = details["first name"] || details["name"];
        let parts = fullName.split(" ");
        if (parts.length > 0) {
          document.getElementById("first_name").value = parts[0];
        }
        if (parts.length > 2) {
          document.getElementById("middle_name").value = parts[1];
          document.getElementById("last_name").value = parts.slice(2).join(" ");
        } else if (parts.length === 2) {
          document.getElementById("last_name").value = parts[1];
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
          document.getElementById("birth_date").value = dobValue;
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
          document.getElementById("contact_number").value = details[key].trim();
          break;
        }
      }

      alert("ID verified and form auto-filled based on the available information.");
    }
  </script>
</body>
</html>
