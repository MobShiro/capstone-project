<?php
// Get current page to highlight active link
$currentPage = basename($_SERVER['PHP_SELF']);
$userType = $_SESSION['user_type'] ?? '';

// Determine current directory level for proper path construction
$currentDir = dirname($_SERVER['PHP_SELF']);
$isSubdir = strpos($currentDir, '/dashboard/') !== false && $currentDir != '/dashboard';
$baseDir = $isSubdir ? '../' : '';
?>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="<?php echo $baseDir; ?>../assets/images/logo.png" alt="Vet Anywhere Logo">
        <h1>Vet Anywhere</h1>
    </div>
    
    <div class="sidebar-menu">
        <h3>Main Menu</h3>
        <ul>
            <?php if ($userType === 'pet_owner'): ?>
                <li>
                    <a href="<?php echo $baseDir; ?>owner_dashboard.php" 
                       class="<?php echo $currentPage === 'owner_dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="<?php echo $baseDir; ?>pets/my_pets.php" 
                       class="<?php echo strpos($currentPage, 'pets/') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-paw"></i> My Pets
                    </a>
                </li>
            <?php elseif ($userType === 'veterinarian'): ?>
                <li>
                    <a href="<?php echo $baseDir; ?>vet_dashboard.php" 
                       class="<?php echo $currentPage === 'vet_dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="<?php echo $baseDir; ?>patients/all_patients.php" 
                       class="<?php echo strpos($currentPage, 'patients/') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-paw"></i> Patients
                    </a>
                </li>
            <?php endif; ?>
            
            <li>
                <a href="<?php echo $baseDir; ?>appointments/upcoming.php" 
                   class="<?php echo strpos($currentPage, 'appointments/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> Appointments
                </a>
            </li>
            
            <?php if ($userType === 'pet_owner'): ?>
                <li>
                    <a href="<?php echo $baseDir; ?>medical_records/view.php" 
                       class="<?php echo strpos($currentPage, 'medical_records/') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i> Medical Records
                    </a>
                </li>
            <?php elseif ($userType === 'veterinarian'): ?>
                <li>
                    <a href="<?php echo $baseDir; ?>medical_records/create.php" 
                       class="<?php echo strpos($currentPage, 'medical_records/') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i> Medical Records
                    </a>
                </li>
            <?php endif; ?>
            
            <li>
                <a href="<?php echo $baseDir; ?>reminders/view.php" 
                   class="<?php echo strpos($currentPage, 'reminders/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i> Reminders
                </a>
            </li>
            
            <li>
                <a href="<?php echo $baseDir; ?>messages/inbox.php" 
                   class="<?php echo strpos($currentPage, 'messages/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-envelope"></i> Messages
                </a>
            </li>
        </ul>
    </div>
    
    <div class="sidebar-menu">
        <h3>More</h3>
        <ul>
            <?php if ($userType === 'pet_owner'): ?>
                <li>
                    <a href="<?php echo $baseDir; ?>vets/find.php" 
                       class="<?php echo strpos($currentPage, 'vets/') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-user-md"></i> Find Veterinarian
                    </a>
                </li>
                <li>
                    <a href="<?php echo $baseDir; ?>vaccinations/schedule.php" 
                       class="<?php echo strpos($currentPage, 'vaccinations/') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-syringe"></i> Vaccinations
                    </a>
                </li>
            <?php elseif ($userType === 'veterinarian'): ?>
                <li>
                    <a href="<?php echo $baseDir; ?>schedule/availability.php" 
                       class="<?php echo strpos($currentPage, 'schedule/') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i> My Schedule
                    </a>
                </li>
                <li>
                    <a href="<?php echo $baseDir; ?>clinic/settings.php" 
                       class="<?php echo strpos($currentPage, 'clinic/') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-clinic-medical"></i> Clinic Settings
                    </a>
                </li>
            <?php endif; ?>
            
            <li>
                <a href="<?php echo $baseDir; ?>reports/view.php" 
                   class="<?php echo strpos($currentPage, 'reports/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            </li>
        </ul>
    </div>
    
    <div class="sidebar-menu">
        <h3>Account</h3>
        <ul>
            <li>
                <a href="<?php echo $baseDir; ?>profile/settings.php" 
                   class="<?php echo strpos($currentPage, 'profile/') !== false ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </a>
            </li>
            <li>
                <a href="<?php echo $baseDir; ?>../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>
    
    <div class="sidebar-footer">
        <p>&copy; <?php echo date('Y'); ?> Vet Anywhere</p>
    </div>
</div>