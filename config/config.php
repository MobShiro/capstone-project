<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Default XAMPP username
define('DB_PASS', '');     // Default XAMPP password
define('DB_NAME', 'vet_anywhere');

// Application settings
define('SITE_NAME', 'Vet Anywhere');
define('SITE_URL', 'http://localhost/vet_anywhere');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx']);

// Email configuration (for sending reminders and notifications)
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'noreply@vetanywhere.com');
define('MAIL_PASSWORD', 'your_email_password');
define('MAIL_FROM_ADDRESS', 'noreply@vetanywhere.com');
define('MAIL_FROM_NAME', 'Vet Anywhere System');

// Session settings
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

// Security settings
define('HASH_COST', 10); // Cost parameter for password hashing
?>