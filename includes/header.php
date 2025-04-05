<?php
if (!defined('BASE_URL')) {
    require_once(__DIR__ . '/../config/config.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? h($page_title) . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/styles.css">
</head>
<body>
    <header class="main-header">
        <nav class="navbar">
            <div class="container">
                <div class="nav-brand">
                    <a href="<?php echo BASE_URL; ?>"><?php echo SITE_NAME; ?></a>
                </div>
                <div class="nav-menu">
                    <?php if (is_logged_in()): ?>
                        <ul>
                            <li><a href="<?php echo BASE_URL; ?>/dashboard.php">Dashboard</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/pets.php">My Pets</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/appointments.php">Appointments</a></li>
                            <li class="dropdown">
                                <a href="#" class="dropdown-toggle"><?php echo h($_SESSION['username']); ?></a>
                                <ul class="dropdown-menu">
                                    <li><a href="<?php echo BASE_URL; ?>/profile.php">Profile</a></li>
                                    <li><a href="<?php echo BASE_URL; ?>/auth/logout.php">Logout</a></li>
                                </ul>
                            </li>
                        </ul>
                    <?php else: ?>
                        <ul>
                            <li><a href="<?php echo BASE_URL; ?>/auth/login.php">Login</a></li>
                            <li><a href="<?php echo BASE_URL; ?>/auth/register.php">Register</a></li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
        <?php echo display_flash_messages(); ?>
    </header>