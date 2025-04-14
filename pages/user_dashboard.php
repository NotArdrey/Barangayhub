<?php
session_start();
require "../config/dbconn.php";
header("Cross-Origin-Opener-Policy: same-origin-allow-popups");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Barangay Hub - Community Portal</title>
  <!-- Link to the separated CSS file -->
  <link rel="stylesheet" href="../styles/user_dashboard.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
  <!-- Navigation Bar -->
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
        <a href="#home">Home</a>
        <a href="#about">About</a>
        <a href="#services">Services</a>
        <a href="#contact">Contact</a>
        <!-- Edit Account Option Added Here -->
        <a href="../pages/edit_account.php">Account</a>
      </div>
    </nav>
  </header>

  <main>
    <!-- Hero Section -->
    <section class="hero" id="home">
      <div class="hero-overlay"></div>
      <div class="hero-content" data-aos="fade-up">
        <h1>Welcome to Barangay Hub</h1>
        <p>Your one-stop platform for all barangay services</p>
        <a href="#services" class="btn cta-button">Explore Services</a>
      </div>
    </section>

    <!-- About Section -->
    <section class="about-section" id="about" data-aos="fade-up">
      <div class="section-header">
        <h2>About Us</h2>
        <p>Learn more about Barangay Hub</p>
      </div>
      <div class="about-content">
        <div class="about-card history">
          <h3>Our History</h3>
          <p>
            Barangay Hub was created to unify all barangay services under one platform.
            Our goal is to foster community engagement and simplify access to essential services.
          </p>
        </div>
        <div class="about-card mission">
          <h3>Our Mission</h3>
          <p>
            Deliver accessible and efficient services for all residents by consolidating barangay services
            in one easy-to-use platform.
          </p>
        </div>
        <div class="about-card vision">
          <h3>Our Vision</h3>
          <p>
            Empower our community through digital innovation, ensuring that every resident can access vital services effortlessly.
          </p>
        </div>
      </div>
    </section>

    <!-- Services Section -->
    <section class="services-section" id="services">
      <div class="section-header">
        <h2>Our Services</h2>
        <p>Access a variety of barangay services online</p>
      </div>
      <div class="services-container">
        <div class="services-list">
          <!-- Barangay Clearance -->
          <div class="service-item" onclick="window.location.href='../pages/services.php?service=barangayClearance';" style="cursor:pointer;">
            <div class="service-icon">
              <i class="fas fa-file-alt"></i>
            </div>
            <div class="service-content">
              <h3>Barangay Clearance</h3>
              <p>Obtain official barangay clearance for various transactions and requirements.</p>
              <a href="../pages/services.php?service=barangayClearance" class="service-cta">
                Get Started
                <i class="fas fa-arrow-right arrow-icon"></i>
              </a>
            </div>
          </div>

          <!-- First Time Job Seeker -->
          <div class="service-item" onclick="window.location.href='../pages/services.php';" style="cursor:pointer;">
            <div class="service-icon">
              <i class="fas fa-briefcase"></i>
            </div>
            <div class="service-content">
              <h3>First Time Job Seeker</h3>
              <p>Assistance and certification for first-time job seekers in the community.</p>
              <a href="../pages/services.php" class="service-cta">
                Apply Now
                <i class="fas fa-arrow-right arrow-icon"></i>
              </a>
            </div>
          </div>

          <!-- Proof of Residency -->
          <div class="service-item" onclick="window.location.href='../pages/services.php?service=proofOfResidency';" style="cursor:pointer;">
            <div class="service-icon">
              <i class="fas fa-home"></i>
            </div>
            <div class="service-content">
              <h3>Proof of Residency</h3>
              <p>Get official certification of your residency status for legal and administrative purposes.</p>
              <a href="../pages/services.php?service=proofOfResidency" class="service-cta">
                Request Certificate
                <i class="fas fa-arrow-right arrow-icon"></i>
              </a>
            </div>
          </div>

          <!-- Barangay Indigency -->
          <div class="service-item" onclick="window.location.href='../pages/services.php?service=barangayIndigency';" style="cursor:pointer;">
            <div class="service-icon">
              <i class="fas fa-hand-holding-heart"></i>
            </div>
            <div class="service-content">
              <h3>Barangay Indigency</h3>
              <p>Obtain certification for social welfare and financial assistance programs.</p>
              <a href="../pages/services.php?service=barangayIndigency" class="service-cta">
                Apply Here
                <i class="fas fa-arrow-right arrow-icon"></i>
              </a>
            </div>
          </div>

          <!-- Good Moral Certificate -->
          <div class="service-item" onclick="window.location.href='../pages/services.php?service=goodMoralCertificate';" style="cursor:pointer;">
            <div class="service-icon">
              <i class="fas fa-user-check"></i>
            </div>
            <div class="service-content">
              <h3>Good Moral Certificate</h3>
              <p>Request certification of good moral character for employment and education purposes.</p>
              <a href="../pages/services.php?service=goodMoralCertificate" class="service-cta">
                Get Certified
                <i class="fas fa-arrow-right arrow-icon"></i>
              </a>
            </div>
          </div>

          <!-- No Income Certification -->
          <div class="service-item" onclick="window.location.href='../pages/services.php?service=noIncomeCertification';" style="cursor:pointer;">
            <div class="service-icon">
              <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="service-content">
              <h3>No Income Certification</h3>
              <p>Official certification for individuals without regular income source.</p>
              <a href="../pages/services.php?service=noIncomeCertification" class="service-cta">
                Request Now
                <i class="fas fa-arrow-right arrow-icon"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section" id="contact" data-aos="fade-up">
      <div class="section-header">
        <h2>Contact Us</h2>
        <p>We'd love to hear from you</p>
      </div>
      <div class="contact-content">
        <div class="contact-form">
          <h3>Send Us a Message</h3>
          <form>
            <div class="form-group">
              <label for="name">Your Name</label>
              <input id="name" type="text" placeholder="Your Name" required />
            </div>
            <div class="form-group">
              <label for="email">Your Email</label>
              <input id="email" type="email" placeholder="Your Email" required />
            </div>
            <div class="form-group">
              <label for="message">Your Message</label>
              <textarea id="message" rows="5" placeholder="Your Message" required></textarea>
            </div>
            <button type="submit" class="btn cta-button">Submit</button>
          </form>
        </div>
      </div>
    </section>
  </main>

  <!-- Footer -->
  <footer class="footer">
    <p>&copy; 2025 Barangay Hub. All rights reserved.</p>
  </footer>

  <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
  <script>
    // Initialize AOS animations
    AOS.init({
      duration: 1000,
      once: true,
      easing: 'ease-out-quad'
    });

    // Mobile Menu Toggle
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');
    menuBtn.addEventListener('click', () => {
      navLinks.classList.toggle('active');
      menuBtn.classList.toggle('active');
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        const headerHeight = document.querySelector('.navbar').offsetHeight;
        const targetPosition = target.offsetTop - headerHeight;
        window.scrollTo({
          top: targetPosition,
          behavior: 'smooth'
        });
      });
    });

    // Lazy loading for images
    const lazyImages = document.querySelectorAll('img[data-src]');
    const lazyLoad = target => {
      const io = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const img = entry.target;
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
            observer.disconnect();
          }
        });
      });
      io.observe(target);
    };
    lazyImages.forEach(lazyLoad);
  </script>
</body>
</html>

<?php
  if(isset($_SESSION['alert'])) {
    echo $_SESSION['alert'];
    unset($_SESSION['alert']);
  }
?> 
