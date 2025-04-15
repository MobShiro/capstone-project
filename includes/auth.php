<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/functions.php';

// Start session if not already started
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Register a new user
function registerUser($username, $password, $email, $firstName, $lastName, $userType, $phone = null, $address = null) {
    $conn = getDbConnection();
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => HASH_COST]);
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, first_name, last_name, user_type, phone, address, registration_date) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssssss", $username, $hashedPassword, $email, $firstName, $lastName, $userType, $phone, $address);
    
    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        
        // If user is a veterinarian, create an entry in vet_profiles
        if ($userType === 'veterinarian') {
            $stmt = $conn->prepare("INSERT INTO vet_profiles (vet_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $userId);
            $stmt->execute();
        }
        
        return ['success' => true, 'user_id' => $userId];
    } else {
        return ['success' => false, 'message' => 'Registration failed: ' . $conn->error];
    }
}

// Login a user
function loginUser($username, $password) {
    $conn = getDbConnection();
    
    // Get user by username or email
    $stmt = $conn->prepare("SELECT user_id, username, password, email, first_name, last_name, user_type, status 
                           FROM users 
                           WHERE (username = ? OR email = ?)");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check if account is active
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'Your account is not active. Please contact support.'];
        }
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Start session
            initSession();
            
            // Update last login time
            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $updateStmt->bind_param("i", $user['user_id']);
            $updateStmt->execute();
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            $_SESSION['last_activity'] = time();
            
            return ['success' => true, 'user' => $user];
        }
    }
    
    return ['success' => false, 'message' => 'Invalid username or password'];
}

// Check if the user is logged in
function isLoggedIn() {
    initSession();
    
    // Check if user is logged in and session is not expired
    if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] < SESSION_TIMEOUT) {
            // Update last activity time
            $_SESSION['last_activity'] = time();
            return true;
        } else {
            // Session expired
            logout();
        }
    }
    
    return false;
}

// Check if user has a specific role
function hasRole($role) {
    initSession();
    
    return isLoggedIn() && $_SESSION['user_type'] === $role;
}

// Get current user information
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT user_id, username, email, first_name, last_name, user_type, phone, address, profile_image, registration_date, last_login 
                           FROM users 
                           WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Logout user
function logout() {
    initSession();
    
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    return true;
}

// Reset password functionality
function generatePasswordResetToken($email) {
    $conn = getDbConnection();
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id, first_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store token in database (you would need to create a password_reset_tokens table)
        // For simplicity, we'll just return the token here
        
        // Send email with reset link (simplified)
        $resetLink = SITE_URL . '/reset-password.php?token=' . $token;
        $to = $email;
        $subject = 'Password Reset for Vet Anywhere';
        $message = "Hello " . $user['first_name'] . ",\n\n";
        $message .= "You have requested to reset your password. Please click the link below to reset your password:\n\n";
        $message .= $resetLink . "\n\n";
        $message .= "This link will expire in 1 hour.\n\n";
        $message .= "If you didn't request this, you can safely ignore this email.\n\n";
        $message .= "Best regards,\nThe Vet Anywhere Team";
        
        // In a real system, you would use a proper email sending library
        mail($to, $subject, $message);
        
        return ['success' => true, 'message' => 'Password reset link has been sent to your email.'];
    }
    
    return ['success' => false, 'message' => 'Email not found.'];
}
?>