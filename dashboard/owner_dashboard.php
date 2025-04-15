<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a pet owner
if (!isLoggedIn() || !hasRole('pet_owner')) {
    header('Location: ../index.php');
    exit;
}

// Get current user information
$user = getCurrentUser();

// Get user's pets
$conn = getDbConnection();
$stmt = $conn->prepare("SELECT * FROM pets WHERE owner_id = ?");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$petsResult = $stmt->get_result();
$pets = [];
while ($pet = $petsResult->fetch_assoc()) {
    $pets[] = $pet;
}

// Get upcoming appointments
$appointments = getUpcomingAppointments($user['user_id'], 'pet_owner', 30);

// Get upcoming reminders
$reminders = getUpcomingReminders($user['user_id'], 7);

// Get recent messages
$stmt = $conn->prepare("SELECT m.*, 
                       CONCAT(u.first_name, ' ', u.last_name) as sender_name,
                       u.user_type as sender_type
                       FROM messages m
                       JOIN users u ON m.sender_id = u.user_id
                       WHERE m.receiver_id = ?
                       ORDER BY m.sent_date DESC
                       LIMIT 5");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$messagesResult = $stmt->get_result();
$messages = [];
while ($message = $messagesResult->fetch_assoc()) {
    $messages[] = $message;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard - Vet Anywhere</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <?php include 'includes/header.php'; ?>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <div class="welcome-section">
                    <h1>Welcome, <?php echo $user['first_name']; ?>!</h1>
                    <p class="date"><?php echo date('l, F j, Y'); ?></p>
                </div>

                <!-- Dashboard Overview -->
                <div class="dashboard-cards">
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-paw"></i>
                        </div>
                        <div class="card-content">
                            <h3>My Pets</h3>
                            <p class="card-value"><?php echo count($pets); ?></p>
                        </div>
                        <a href="pets/my_pets.php" class="card-link">View All</a>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="card-content">
                            <h3>Upcoming Appointments</h3>
                            <p class="card-value"><?php echo count($appointments); ?></p>
                        </div>
                        <a href="appointments/upcoming.php" class="card-link">View All</a>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="card-content">
                            <h3>Reminders</h3>
                            <p class="card-value"><?php echo count($reminders); ?></p>
                        </div>
                        <a href="reminders/view.php" class="card-link">View All</a>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="card-content">
                            <h3>Messages</h3>
                            <p class="card-value"><?php 
                                $unreadCount = 0;
                                foreach ($messages as $message) {
                                    if (!$message['is_read']) $unreadCount++;
                                }
                                echo $unreadCount; 
                            ?></p>
                        </div>
                        <a href="messages/inbox.php" class="card-link">View All</a>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="action-buttons">
                        <a href="pets/add_pet.php" class="action-btn">
                            <i class="fas fa-plus-circle"></i>
                            <span>Add New Pet</span>
                        </a>
                        <a href="appointments/schedule.php" class="action-btn">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Schedule Appointment</span>
                        </a>
                        <a href="reminders/add.php" class="action-btn">
                            <i class="fas fa-bell"></i>
                            <span>Set Reminder</span>
                        </a>
                        <a href="vets/find.php" class="action-btn">
                            <i class="fas fa-search"></i>
                            <span>Find Veterinarian</span>
                        </a>
                    </div>
                </div>

                <!-- Sections Row -->
                <div class="dashboard-row">
                    <!-- Upcoming Appointments -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Upcoming Appointments</h2>
                            <a href="appointments/upcoming.php" class="view-all">View All</a>
                        </div>
                        
                        <?php if (empty($appointments)): ?>
                            <div class="empty-state">
                                <img src="../assets/images/empty-appointments.svg" alt="No Appointments">
                                <p>No upcoming appointments scheduled</p>
                                <a href="appointments/schedule.php" class="btn-primary">Schedule Now</a>
                            </div>
                        <?php else: ?>
                            <div class="appointments-list">
                                <?php foreach (array_slice($appointments, 0, 3) as $appointment): ?>
                                    <div class="appointment-card">
                                        <div class="appointment-date">
                                            <?php 
                                                $date = new DateTime($appointment['appointment_date']);
                                                echo '<div class="date-day">' . $date->format('d') . '</div>';
                                                echo '<div class="date-month">' . $date->format('M') . '</div>';
                                            ?>
                                        </div>
                                        <div class="appointment-details">
                                            <h4><?php echo htmlspecialchars($appointment['purpose']); ?></h4>
                                            <p>
                                                <i class="fas fa-clock"></i> 
                                                <?php echo $date->format('g:i A'); ?>
                                            </p>
                                            <p>
                                                <i class="fas fa-user-md"></i> 
                                                Dr. <?php echo htmlspecialchars($appointment['vet_name']); ?>
                                            </p>
                                            <p>
                                                <i class="fas fa-paw"></i> 
                                                <?php echo htmlspecialchars($appointment['pet_name']); ?>
                                            </p>
                                        </div>
                                        <div class="appointment-actions">
                                            <a href="appointments/view.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn-outline">Details</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Reminders -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Upcoming Reminders</h2>
                            <a href="reminders/view.php" class="view-all">View All</a>
                        </div>
                        
                        <?php if (empty($reminders)): ?>
                            <div class="empty-state">
                                <img src="../assets/images/empty-reminders.svg" alt="No Reminders">
                                <p>No upcoming reminders</p>
                                <a href="reminders/add.php" class="btn-primary">Add Reminder</a>
                            </div>
                        <?php else: ?>
                            <div class="reminders-list">
                                <?php foreach ($reminders as $reminder): ?>
                                    <div class="reminder-card">
                                        <div class="reminder-icon">
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
                                        <div class="reminder-details">
                                            <h4><?php echo htmlspecialchars($reminder['title']); ?></h4>
                                            <p><i class="fas fa-calendar-alt"></i> <?php echo formatDate($reminder['reminder_date']); ?></p>
                                            <p><i class="fas fa-paw"></i> <?php echo htmlspecialchars($reminder['pet_name']); ?></p>
                                            <?php if (!empty($reminder['description'])): ?>
                                                <p class="reminder-description"><?php echo htmlspecialchars($reminder['description']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="reminder-actions">
                                            <button class="btn-outline mark-complete" data-id="<?php echo $reminder['reminder_id']; ?>">Mark Complete</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- My Pets Section -->
                <div class="my-pets-section">
                    <div class="section-header">
                        <h2>My Pets</h2>
                        <a href="pets/my_pets.php" class="view-all">View All</a>
                    </div>
                    
                    <?php if (empty($pets)): ?>
                        <div class="empty-state">
                            <img src="../assets/images/empty-pets.svg" alt="No Pets">
                            <p>You haven't added any pets yet</p>
                            <a href="pets/add_pet.php" class="btn-primary">Add Your First Pet</a>
                        </div>
                    <?php else: ?>
                        <div class="pets-carousel">
                            <?php foreach ($pets as $pet): ?>
                                <div class="pet-card">
                                    <div class="pet-image">
                                        <?php if ($pet['photo']): ?>
                                            <img src="../uploads/pets/<?php echo $pet['photo']; ?>" alt="<?php echo htmlspecialchars($pet['name']); ?>">
                                        <?php else: ?>
                                            <img src="../assets/images/default-pet.png" alt="Default Pet Image">
                                        <?php endif; ?>
                                    </div>
                                    <div class="pet-info">
                                        <h3><?php echo htmlspecialchars($pet['name']); ?></h3>
                                        <p class="pet-breed">
                                            <?php echo htmlspecialchars($pet['species']); ?>
                                            <?php if ($pet['breed']): ?> - <?php echo htmlspecialchars($pet['breed']); ?><?php endif; ?>
                                        </p>
                                        <p class="pet-age">
                                            <?php echo $pet['date_of_birth'] ? calculateAge($pet['date_of_birth']) : 'Age unknown'; ?>
                                        </p>
                                        <a href="pets/pet_details.php?id=<?php echo $pet['pet_id']; ?>" class="btn-outline">View Details</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>