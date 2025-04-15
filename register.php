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

// Determine user type from query parameter
$userType = isset($_GET['type']) ? $_GET['type'] : 'pet_owner';
if (!in_array($userType, ['pet_owner', 'veterinarian'])) {
    $userType = 'pet_owner'; // Default to pet owner
}

// Process registration form
$registrationError = '';
$registrationSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Get form data
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $firstName = sanitizeInput($_POST['first_name']);
    $lastName = sanitizeInput($_POST['last_name']);
    $phone = sanitizeInput($_POST['phone']);
    $address = sanitizeInput($_POST['address']);
    $userType = sanitizeInput($_POST['user_type']);
    
    // Validate form data
    if (strlen($username) < 4) {
        $registrationError = 'Username must be at least 4 characters long.';
    } elseif (!isValidEmail($email)) {
        $registrationError = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $registrationError = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $registrationError = 'Passwords do not match.';
    } else {
        // Register user
        $result = registerUser($username, $password, $email, $firstName, $lastName, $userType, $phone, $address);
        
        if ($result['success']) {
            $registrationSuccess = true;
        } else {
            $registrationError = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Vet Anywhere</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="register-page">
        <!-- Header -->
        <header class="simple-header">
            <div class="logo">
                <a href="index.php">
                    <img src="assets/images/logo.png" alt="Vet Anywhere Logo">
                    <h1>Vet Anywhere</h1>
                </a>
            </div>
        </header>

        <!-- Registration Form -->
        <section class="registration-form">
            <div class="container">
                <div class="form-header">
                    <h2>Create Your Account</h2>
                    <p>Join Vet Anywhere to manage your pet's health records effectively.</p>
                </div>

                <div class="user-type-selector">
                    <a href="?type=pet_owner" class="user-type <?php echo $userType === 'pet_owner' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i>
                        <span>Pet Owner</span>
                    </a>
                    <a href="?type=veterinarian" class="user-type <?php echo $userType === 'veterinarian' ? 'active' : ''; ?>">
                        <i class="fas fa-user-md"></i>
                        <span>Veterinarian</span>
                    </a>
                </div>

                <?php if ($registrationSuccess): ?>
                    <div class="success-message">
                        <i class="fas fa-check-circle"></i>
                        <h3>Registration Successful!</h3>
                        <p>Your account has been created successfully. You can now login to access your dashboard.</p>
                        <a href="index.php#login-modal" class="btn-primary">Login Now</a>
                    </div>
                <?php else: ?>
                    <?php if ($registrationError): ?>
                        <div class="error-message"><?php echo $registrationError; ?></div>
                    <?php endif; ?>

                    <form method="post" action="">
                        <input type="hidden" name="user_type" value="<?php echo $userType; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name <span class="required">*</span></label>
                                <input type="text" id="first_name" name="first_name" required>
                            </div>
                            <div class="form-group">
                                <label for="last_name">Last Name <span class="required">*</span></label>
                                <input type="text" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username <span class="required">*</span></label>
                            <input type="text" id="username" name="username" required>
                            <small>Choose a unique username (at least 4 characters)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" rows="3"></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">Password <span class="required">*</span></label>
                                <input type="password" id="password" name="password" required>
                                <small>At least 8 characters long</small>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <?php if ($userType === 'veterinarian'): ?>
                            <div class="veterinarian-fields">
                                <h3>Veterinarian Information</h3>
                                <p>You'll be able to add more professional details after registration.</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="form-group terms">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
                        </div>
                        
                        <button type="submit" name="register" class="btn-primary btn-full">Create Account</button>
                    </form>
                    
                    <div class="form-footer">
                        <p>Already have an account? <a href="index.php#login-modal">Login</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Footer -->
        <footer class="simple-footer">
            <p>&copy; <?php echo date('Y'); ?> Vet Anywhere. All rights reserved.</p>
        </footer>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>