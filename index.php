<?php
require_once 'includes/db_connect.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Check if user is already logged in
if (isLoggedIn()) {
    // Redirect to dashboard based on user type
    if ($_SESSION['user_type'] === 'pet_owner') {
        header('Location: dashboard/owner_dashboard.php');
    } elseif ($_SESSION['user_type'] === 'veterinarian') {
        header('Location: dashboard/vet_dashboard.php');
    } elseif ($_SESSION['user_type'] === 'admin') {
        header('Location: dashboard/admin_dashboard.php');
    }
    exit;
}

// Process login form
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    $result = loginUser($username, $password);
    
    if ($result['success']) {
        // Redirect based on user type
        if ($result['user']['user_type'] === 'pet_owner') {
            header('Location: dashboard/owner_dashboard.php');
        } elseif ($result['user']['user_type'] === 'veterinarian') {
            header('Location: dashboard/vet_dashboard.php');
        } elseif ($result['user']['user_type'] === 'admin') {
            header('Location: dashboard/admin_dashboard.php');
        }
        exit;
    } else {
        $loginError = $result['message'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vet Anywhere - Pet Health Management System</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="landing-page">
        <!-- Header -->
        <header>
            <div class="logo">
                <h1>Vet Anywhere</h1>
            </div>
            <nav>
                <ul>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#how-it-works">How It Works</a></li>
                    <li><a href="#testimonials">Testimonials</a></li>
                    <li><a href="register.php" class="btn-secondary">Register</a></li>
                    <li><a href="#login-modal" class="btn-primary modal-trigger">Login</a></li>
                </ul>
            </nav>
            <div class="mobile-menu">
                <i class="fas fa-bars"></i>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-content">
                <h1>The Complete Pet Health Management System</h1>
                <p>Keep track of your pet's medical records, vaccinations, and appointments all in one place.</p>
                <div class="cta-buttons">
                    <a href="register.php?type=pet_owner" class="btn-primary">I'm a Pet Owner</a>
                    <a href="register.php?type=veterinarian" class="btn-secondary">I'm a Veterinarian</a>
                </div>
            </div>
            <div class="hero-image">
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="features">
            <h2>Features</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Medical Records</h3>
                    <p>Keep all your pet's medical history in one secure location.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-syringe"></i>
                    <h3>Vaccination Tracking</h3>
                    <p>Never miss a vaccination with automated reminders.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Appointment Scheduling</h3>
                    <p>Schedule and manage vet appointments with ease.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-pills"></i>
                    <h3>Medication Management</h3>
                    <p>Track medications, dosages, and refill schedules.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-bell"></i>
                    <h3>Reminders & Alerts</h3>
                    <p>Get timely notifications for upcoming pet care needs.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-comments"></i>
                    <h3>Vet Communication</h3>
                    <p>Direct messaging with your veterinarian for quick consultations.</p>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section id="how-it-works" class="how-it-works">
            <h2>How It Works</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <h3>Create Your Account</h3>
                    <p>Register as a pet owner or veterinarian to get started.</p>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <h3>Add Your Pets</h3>
                    <p>Create profiles for each of your pets with their details.</p>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <h3>Connect with Vets</h3>
                    <p>Find and connect with veterinarians for your pet's care.</p>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <h3>Manage Health Records</h3>
                    <p>Keep track of all health-related information in one place.</p>
                </div>
            </div>
        </section>

        <!-- Testimonials Section -->
        <section id="testimonials" class="testimonials">
            <h2>What Our Users Say</h2>
            <div class="testimonial-slider">
                <div class="testimonial">
                    <div class="testimonial-content">
                        <p>"Vet Anywhere has made managing my pets' health records so much easier. I love getting reminders for vaccinations and appointments!"</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="assets/images/testimonial-1.jpg" alt="Sarah J.">
                        <div>
                            <h4>Sarah J.</h4>
                            <p>Pet Owner</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial">
                    <div class="testimonial-content">
                        <p>"As a veterinarian, this system has streamlined my practice and improved communication with pet owners. The complete medical history at my fingertips is invaluable."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="assets/images/testimonial-2.jpg" alt="Dr. Michael T.">
                        <div>
                            <h4>Dr. Michael T.</h4>
                            <p>Veterinarian</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial">
                    <div class="testimonial-content">
                        <p>"I have three pets with different medical needs. Vet Anywhere helps me keep everything organized and ensures they all get the care they need on time."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="assets/images/testimonial-3.jpg" alt="Robert L.">
                        <div>
                            <h4>Robert L.</h4>
                            <p>Pet Owner</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="slider-controls">
                <button class="prev-btn"><i class="fas fa-chevron-left"></i></button>
                <div class="slider-dots">
                    <span class="dot active"></span>
                    <span class="dot"></span>
                    <span class="dot"></span>
                </div>
                <button class="next-btn"><i class="fas fa-chevron-right"></i></button>
            </div>
        </section>

        <!-- Footer -->
        <footer>
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="assets/images/logo.png" alt="Vet Anywhere Logo">
                    <h3>Vet Anywhere</h3>
                </div>
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="#features">Features</a></li>
                        <li><a href="#how-it-works">How It Works</a></li>
                        <li><a href="#testimonials">Testimonials</a></li>
                        <li><a href="register.php">Register</a></li>
                    </ul>
                </div>
                <div class="footer-links">
                    <h4>Resources</h4>
                    <ul>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                        <li><a href="#">Contact Us</a></li>
                    </ul>
                </div>
                <div class="footer-newsletter">
                    <h4>Stay Updated</h4>
                    <p>Subscribe to our newsletter for tips on pet health care.</p>
                    <form>
                        <input type="email" placeholder="Enter your email">
                        <button type="submit" class="btn-primary">Subscribe</button>
                    </form>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Vet Anywhere. All rights reserved.</p>
            </div>
        </footer>

        <!-- Login Modal -->
        <div id="login-modal" class="modal">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Login to Vet Anywhere</h2>
                <?php if ($loginError): ?>
                    <div class="error-message"><?php echo $loginError; ?></div>
                <?php endif; ?>
                <form method="post" action="">
                    <div class="form-group">
                        <label for="username">Username or Email</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group remember">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <button type="submit" name="login" class="btn-primary btn-full">Login</button>
                </form>
                <div class="modal-footer">
                    <p>Don't have an account? <a href="register.php">Register</a></p>
                    <p><a href="forgot-password.php">Forgot Password?</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>