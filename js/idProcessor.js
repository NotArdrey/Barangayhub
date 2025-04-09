// Wait for the DOM to load and attach a listener to the upload input for ID processing
document.addEventListener("DOMContentLoaded", function () {
  const uploadInput = document.getElementById("uploadId");
  if (uploadInput) {
    uploadInput.addEventListener("change", handleImageUpload);
  }
});

// Handles the file input change event; reads the image file as a DataURL
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

// Processes the image: creates a canvas, checks for blur, runs OCR, and auto-fills fields if valid
function processImage(dataURL) {
  const img = new Image();
  img.src = dataURL;
  img.onload = function () {
    // Create a canvas for image processing
    const canvas = document.createElement("canvas");
    canvas.width = img.width;
    canvas.height = img.height;
    const ctx = canvas.getContext("2d");
    ctx.drawImage(img, 0, 0);

    // Check if the image is blurry using OpenCV.js
    if (isImageBlurry(canvas)) {
      alert("The image is too blurry. Please upload a clear image.");
      return;
    }

    // Run OCR on the image using Tesseract.js
    Tesseract.recognize(dataURL, "eng", {
      logger: (m) => console.log(m)
    })
      .then((result) => {
        const ocrText = result.data.text;

        // Verify if the OCR text appears to be from a PH government ID
        if (!verifyPHID(ocrText)) {
          alert("The uploaded ID does not appear to be a valid PH government ID.");
          return;
        }

        // Extract key-value pairs dynamically from the OCR text
        const details = extractDetailsDynamic(ocrText);

        // Check for expiry (if an expiry date exists)
        if (details["expiry"] && isExpired(details["expiry"])) {
          alert("The uploaded ID is expired.");
          return;
        }

        // Autofill the form based on the extracted details
        autofillForm(details);
      })
      .catch((error) => {
        console.error("OCR Error:", error);
        alert("Error processing the ID image.");
      });
  };
}

// Detect blur using the variance of the Laplacian (requires OpenCV.js)
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

    // Clean up allocated matrices
    src.delete();
    gray.delete();
    laplacian.delete();
    mean.delete();
    stddev.delete();

    // Set a threshold for blur detection (tweak if necessary)
    return variance < 100;
  } catch (err) {
    console.error("Error in blur detection:", err);
    // If there's an error, assume the image is acceptable
    return false;
  }
}

// Verify that the OCR text likely comes from a PH government ID
function verifyPHID(text) {
  text = text.toLowerCase();
  return text.includes("republic of the philippines") || text.includes("government");
}

/**
 * Dynamically extract key–value pairs from the OCR text.
 * Assumes each data field is written as "Label: Value".
 * All keys are converted to lower case for uniform mapping.
 */
function extractDetailsDynamic(text) {
  const details = {};
  const lines = text.split('\n');
  lines.forEach((line) => {
    if (line.includes(':')) {
      const parts = line.split(':');
      // Normalize key to lower case and trim whitespace
      const key = parts[0].trim().toLowerCase();
      // In case the value contains additional colons
      const value = parts.slice(1).join(':').trim();
      if (key && value) {
        details[key] = value;
      }
    }
  });
  return details;
}

// Check if the expiry date is in the past (expects MM/DD/YYYY format)
function isExpired(expiryStr) {
  if (!expiryStr) return false; // No expiry date means assume valid
  const parts = expiryStr.split("/");
  if (parts.length !== 3) return false;
  const [month, day, year] = parts.map((p) => parseInt(p, 10));
  // JavaScript month index starts at 0
  const expiryDate = new Date(year, month - 1, day);
  const today = new Date();
  return expiryDate < today;
}

/**
 * Autofills the form fields dynamically based on the details object.
 * Only keys present in the OCR text (extracted via extractDetailsDynamic)
 * are set, using the fieldMapping object to match OCR keys to form element IDs.
 */
function autofillForm(details) {
  // Mapping from OCR keys to form field IDs
  const fieldMapping = {
    "first name": "firstName",
    "middle name": "middleName",
    "last name": "lastName",
    "dob": "birthday", // Assuming "DOB" in OCR becomes "dob" in lower case
    "gender": "gender",
    "contact number": "contactNumber",
    "email": "email",
    "marital status": "maritalStatus",
    "type of residency": "typeOfResidency",
    "senior citizen / pwd": "seniorOrPwd",
    "solo parent": "soloParent",
    "emergency contact name": "emergencyName",
    "emergency contact number": "emergencyNumber",
    "emergency contact address": "emergencyAddress",
    "residency": "residencyType",
    "years in molino iv": "yearsInMolinoIV",
    "block/lot": "blockLot",
    "phase": "phase",
    "street": "street",
    "subdivision": "subdivision",
    "barangay": "barangay",
    "city": "city",
    "province": "province",
    "document type": "documentType",
    "purpose (barangay clearance)": "purposeClearance",
    "duration of residency": "residencyDuration",
    "purpose (proof of residency)": "residencyPurpose",
    "purpose (good moral certificate)": "gmcPurpose",
    "reason for no income certification": "nicReason",
    "monthly income": "indigencyIncome",
    "reason for indigency": "indigencyReason",
    "expiry": "expiry"
  };

  Object.keys(fieldMapping).forEach((key) => {
    const fieldId = fieldMapping[key];
    if (details[key]) {
      let value = details[key];

      // Special formatting: if it's the DOB, convert from MM/DD/YYYY to YYYY-MM-DD
      if (key === "dob") {
        const dateParts = value.split("/");
        if (dateParts.length === 3) {
          const [month, day, year] = dateParts;
          value = `${year}-${month.padStart(2, "0")}-${day.padStart(2, "0")}`;
        }
      }

      // For document type, trigger its change event so that document-specific fields show
      if (fieldId === "documentType") {
        document.getElementById(fieldId).value = value;
        document.getElementById(fieldId).dispatchEvent(new Event("change"));
      } else {
        document.getElementById(fieldId).value = value;
      }
    }
  });

  alert("ID verified and form auto-filled based on the available information.");
}
