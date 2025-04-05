<?php
// Session configuration - MUST come before session_start()
ini_set('session.gc_maxlifetime', 1800);  // Move this line to the top
ini_set('session.cookie_lifetime', 1800);  // Optional: Also set cookie lifetime

// Start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "vet_anywhere";

// Create connection
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Define constants
define('BASE_URL', '/vet_anywhere');
define('SITE_NAME', 'Vet Anywhere');

// Time zone setting
date_default_timezone_set('UTC');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// The session timeout setting was here before - we moved it to the top
?>