<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vet Anywhere - Pet Health Management System</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container">
        <div class="hero-section">
            <h1>Welcome to Vet Anywhere</h1>
            <p>Your comprehensive pet health management solution</p>
        </div>

        <?php if(!isset($_SESSION['user_id'])): ?>
        <div class="card">
            <h2>Login</h2>
            <form action="auth/login.php" method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>
            <p>Don't have an account? <a href="auth/register.php">Register here</a></p>
        </div>
        <?php else: ?>
        <div class="dashboard">
            <div class="card">
                <h2>Quick Actions</h2>
                <div class="quick-actions">
                    <a href="pets/add.php" class="btn btn-primary">Add New Pet</a>
                    <a href="appointments/schedule.php" class="btn btn-primary">Schedule Appointment</a>
                    <a href="records/view.php" class="btn btn-primary">View Medical Records</a>
                </div>
            </div>
            
            <div class="card">
                <h2>Upcoming Appointments</h2>
                <?php include 'includes/upcoming_appointments.php'; ?>
            </div>
            
            <div class="card">
                <h2>Vaccination Reminders</h2>
                <?php include 'includes/vaccination_reminders.php'; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <script src="js/main.js"></script>
</body>
</html>