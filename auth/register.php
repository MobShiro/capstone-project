<?php
require_once('../config/config.php');
require_once('auth.php');

$page_title = 'Register';
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

include '../includes/header.php';
?>

<div class="form-container">
    <h2>Create Your Account</h2>
    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form action="register.php" method="POST">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required 
                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>

        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required
                   placeholder="At least 8 characters">
        </div>

        <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>

        <div class="form-group">
            <label for="user_type">I am a:</label>
            <select id="user_type" name="user_type" required>
                <option value="client" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'client') ? 'selected' : ''; ?>>Pet Owner</option>
                <option value="vet" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'vet') ? 'selected' : ''; ?>>Veterinarian</option>
            </select>
        </div>

        <button type="submit">Create Account</button>
    </form>
    <p>Already have an account? <a href="login.php">Sign in</a></p>
</div>

<?php include '../includes/footer.php'; ?>