<?php
require_once('../config/config.php'); // This will handle session start
require_once('auth.php');

$auth = new Auth($conn);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = trim($_POST['email']);
    $user_type = $_POST['user_type'];

    // Validation
    if (empty($username) || empty($password) || empty($email)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        $result = $auth->register($username, $password, $email, $user_type);
        if ($result['success']) {
            $success = $result['message'];
            header("Refresh:2; url=login.php");
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <!-- Previous CSS styles remain the same -->
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="registration-container">
        <!-- Previous HTML content remains the same -->
    </div>

    <?php include '../includes/footer.php'; ?>
</body>
</html>