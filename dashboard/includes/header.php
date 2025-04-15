<?php
// Get user information for header
$currentUser = getCurrentUser();
?>

<div class="dashboard-header">
    <div class="page-title">
        <?php 
        // Determine page title based on current file
        $currentPage = basename($_SERVER['PHP_SELF']);
        $pageTitle = "Dashboard";
        
        // Set page title based on the current page
        if (strpos($currentPage, 'pets') !== false) {
            $pageTitle = "My Pets";
        } elseif (strpos($currentPage, 'appointments') !== false) {
            $pageTitle = "Appointments";
        } elseif (strpos($currentPage, 'medical_records') !== false) {
            $pageTitle = "Medical Records";
        } elseif (strpos($currentPage, 'reminders') !== false) {
            $pageTitle = "Reminders";
        } elseif (strpos($currentPage, 'messages') !== false) {
            $pageTitle = "Messages";
        } elseif (strpos($currentPage, 'vets') !== false) {
            $pageTitle = "Find Veterinarians";
        } elseif (strpos($currentPage, 'vaccinations') !== false) {
            $pageTitle = "Vaccinations";
        } elseif (strpos($currentPage, 'profile') !== false) {
            $pageTitle = "Profile Settings";
        } elseif (strpos($currentPage, 'reports') !== false) {
            $pageTitle = "Reports";
        }
        ?>
        <h1><?php echo $pageTitle; ?></h1>
    </div>
    
    <div class="header-actions">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search...">
        </div>
        
        <div class="notifications">
            <button class="icon-button">
                <i class="fas fa-bell"></i>
                <?php
                // Count unread notifications/reminders
                $conn = getDbConnection();
                $unreadCount = 0;
                
                // Count upcoming reminders
                $reminders = getUpcomingReminders($currentUser['user_id'], 3);
                $unreadCount += count($reminders);
                
                // Display notification count if greater than 0
                if ($unreadCount > 0):
                ?>
                <span class="count"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </button>
            
            <!-- Notifications Dropdown -->
            <div class="notifications-dropdown" style="display: none;">
                <div class="dropdown-header">
                    <h3>Notifications</h3>
                    <a href="../reminders/view.php">View All</a>
                </div>
                
                <div class="dropdown-content">
                    <?php if (empty($reminders)): ?>
                        <div class="empty-notifications">
                            <p>No new notifications</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($reminders as $reminder): ?>
                            <div class="notification-item">
                                <div class="notification-icon">
                                    <?php 
                                        switch($reminder['reminder_type']) {
                                            case 'Vaccination':
                                                echo '<i class="fas fa-syringe"></i>';
                                                break;
                                            case 'Medication':
                                                echo '<i class="fas fa-pills"></i>';
                                                break;
                                            case 'Appointment':
                                                echo '<i class="fas fa-calendar-check"></i>';
                                                break;
                                            case 'Checkup':
                                                echo '<i class="fas fa-stethoscope"></i>';
                                                break;
                                            default:
                                                echo '<i class="fas fa-bell"></i>';
                                        }
                                    ?>
                                </div>
                                <div class="notification-content">
                                    <p class="notification-title"><?php echo htmlspecialchars($reminder['title']); ?></p>
                                    <p class="notification-text">
                                        Pet: <?php echo htmlspecialchars($reminder['pet_name']); ?><br>
                                        Due: <?php echo formatDate($reminder['reminder_date']); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="user-menu">
            <button class="user-button">
                <?php if ($currentUser['profile_image']): ?>
                    <img class="user-avatar" src="../uploads/profile/<?php echo $currentUser['profile_image']; ?>" alt="<?php echo $currentUser['first_name']; ?>">
                <?php else: ?>
                    <img class="user-avatar" src="../assets/images/default-avatar.png" alt="Default Avatar">
                <?php endif; ?>
                <span class="user-name"><?php echo $currentUser['first_name']; ?></span>
                <i class="fas fa-chevron-down"></i>
            </button>
            
            <!-- User Dropdown -->
            <div class="dropdown-content" style="display: none;">
                <a href="../dashboard/profile/settings.php">
                    <i class="fas fa-user-cog"></i> Profile Settings
                </a>
                <?php if ($currentUser['user_type'] === 'pet_owner'): ?>
                    <a href="../dashboard/pets/my_pets.php">
                        <i class="fas fa-paw"></i> My Pets
                    </a>
                <?php endif; ?>
                <a href="../dashboard/messages/inbox.php">
                    <i class="fas fa-envelope"></i> Messages
                </a>
                <a href="../dashboard/reminders/view.php">
                    <i class="fas fa-bell"></i> Reminders
                </a>
                <a href="../logout.php" class="logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Sidebar Toggle Button -->
<button class="mobile-sidebar-toggle">
    <i class="fas fa-bars"></i>
</button>