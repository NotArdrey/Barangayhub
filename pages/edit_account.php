<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
  
  $user_id = $_SESSION['user_id'];
  
  $query = "SELECT email FROM Users WHERE id = ?";
  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $user_id);
  $stmt->execute();
  $result = $stmt->get_result();
  $user = $result->fetch_assoc();
  $email = $user['email'] ?? '';
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Barangay Hub - Edit Account</title>
  <link rel="stylesheet" href="../styles/edit_account.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

</head>
<body>
  <!-- Navbar remains intact -->
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
        <a href="user_dashboard.php">Home</a>
        <a href="#about">About</a>
        <a href="#services">Services</a>
        <a href="#contact">Contact</a>
        <a href="edit_account.php" class="active">Edit Account</a>
      </div>
    </nav>
  </header>

  <main>
    <section class="edit-account-section">
      <div class="section-header">
        <h2>Edit Account Information</h2>
        <p>Update your password below</p>
      </div>

      <div class="account-form-container">
        <!-- Form submission handled by update_password.php -->
        <form class="account-form" method="POST" action="../functions/edit_account.php">
          <!-- Email Field (Non-Editable) -->
          <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" value="<?php echo htmlspecialchars($email); ?>" disabled>
          </div>

          <!-- Password Update Section -->
          <div class="password-update">
  <h3>Change Password</h3>

  <!-- Current Password Field -->
  <div class="form-group">
    <label for="current-password">Current Password</label>
    <div class="password-container">
      <input type="password" id="current-password" name="current_password" placeholder="••••••••" required>
      <button type="button" class="toggle-password" aria-label="Toggle password visibility">
        <div class="eye-icon">
          <svg viewBox="0 0 24 24">
            <path d="M12 5C5.64 5 1 12 1 12s4.64 7 11 7 11-7 11-7-4.64-7-11-7zm0 12c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/>
            <circle cx="12" cy="12" r="2.5"/>
          </svg>
          <div class="eye-slash"></div>
        </div>
      </button>
    </div>
  </div>

        <!-- New Password Field -->
        <div class="form-group">
            <label for="new-password">New Password</label>
            <div class="password-container">
            <input type="password" id="new-password" name="new_password" placeholder="••••••••" required>
            <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                <div class="eye-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M12 5C5.64 5 1 12 1 12s4.64 7 11 7 11-7 11-7-4.64-7-11-7zm0 12c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/>
                    <circle cx="12" cy="12" r="2.5"/>
                </svg>
                <div class="eye-slash"></div>
                </div>
            </button>
            </div>
        </div>

        <!-- Confirm Password Field -->
        <div class="form-group">
            <label for="confirm-password">Confirm Password</label>
            <div class="password-container">
            <input type="password" id="confirm-password" name="confirm_password" placeholder="••••••••" required>
            <button type="button" class="toggle-password" aria-label="Toggle password visibility">
                <div class="eye-icon">
                <svg viewBox="0 0 24 24">
                    <path d="M12 5C5.64 5 1 12 1 12s4.64 7 11 7 11-7 11-7-4.64-7-11-7zm0 12c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/>
                    <circle cx="12" cy="12" r="2.5"/>
                </svg>
                <div class="eye-slash"></div>
                </div>
            </button>
            </div>
        </div>
        </div>

          <!-- Form Actions -->
          <div class="form-actions">
            <button type="reset" class="btn secondary-btn">Cancel</button>
            <button type="submit" class="btn cta-button">Save Changes</button>
          </div>
        </form>
      </div>
    </section>
  </main>

  <footer class="footer">
    <p>&copy; 2025 Barangay Hub. All rights reserved.</p>
  </footer>

  <script>
    // Mobile Menu Toggle
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');
    menuBtn.addEventListener('click', () => {
      navLinks.classList.toggle('active');
      menuBtn.classList.toggle('active');
    });
  </script>
</body>
</html>
<script>
  // Attach event listeners to all toggle-password buttons in the edit account page
  document.querySelectorAll('.toggle-password').forEach(function(toggleBtn) {
    toggleBtn.addEventListener('click', function() {
      // Find the input within the same container
      const input = this.parentElement.querySelector('input');
      if (input) {
        // Toggle between 'password' and 'text'
        input.setAttribute('type', input.getAttribute('type') === 'password' ? 'text' : 'password');
        // Toggle a class for styling the button if needed
        this.classList.toggle('visible');
      }
    });
  });
</script>

<?php
            if(isset($_SESSION['alert'])) {
            echo $_SESSION['alert'];
            unset($_SESSION['alert']);
            }
?>