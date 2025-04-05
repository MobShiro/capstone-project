<?php
session_start();
require_once('../config.php');
require_once('../pets/pets.php');
require_once('../appointments/appointments.php');
require_once('../medical/medical_records.php');
require_once('../notifications/notifications.php');

// Check if user is logged in and is a pet owner
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'owner') {
    header('Location: ../index.php');
    exit();
}

$petManager = new PetManager($conn);
$appointmentManager = new AppointmentManager($conn);
$medicalManager = new MedicalRecordManager($conn);
$notificationManager = new NotificationManager($conn);

$pets = $petManager->getUserPets();
$upcomingAppointments = $appointmentManager->getUpcomingAppointments();
$dueVaccinations = $medicalManager->getDueVaccinations();
$notifications = $notificationManager->getUserNotifications();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pet Owner Dashboard - Vet Anywhere</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <?php include('../includes/header.php'); ?>
    
    <div class="container">
        <div class="dashboard-header">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
            <p class="current-time">Current Time (UTC): <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>

        <div class="dashboard-grid">
            <!-- Pets Section -->
            <div class="card">
                <h2>My Pets</h2>
                <?php if (empty($pets)): ?>
                    <p>No pets registered yet.</p>
                    <a href="../pets/add.php" class="btn btn-primary">Add New Pet</a>
                <?php else: ?>
                    <div class="pet-list">
                        <?php foreach ($pets as $pet): ?>
                            <div class="pet-item">
                                <h3><?php echo htmlspecialchars($pet['name']); ?></h3>
                                <p>Species: <?php echo htmlspecialchars($pet['species']); ?></p>
                                <p>Breed: <?php echo htmlspecialchars($pet['breed']); ?></p>
                                <div class="pet-actions">
                                    <a href="../pets/view.php?id=<?php echo $pet['pet_id']; ?>" class="btn">View Details</a>
                                    <a href="../appointments/schedule.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn">Schedule Visit</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Appointments -->
            <div class="card">
                <h2>Upcoming Appointments</h2>
                <?php if (empty($upcomingAppointments)): ?>
                    <p>No upcoming appointments.</p>
                <?php else: ?>
                    <div class="appointment-list">
                        <?php foreach ($upcomingAppointments as $appointment): ?>
                            <div class="appointment-item">
                                <p class="appointment-date">
                                    <?php echo date('M d, Y H:i', strtotime($appointment['appointment_date'])); ?>
                                </p>
                                <p class="appointment-pet">
                                    Pet: <?php echo htmlspecialchars($appointment['pet_name']); ?>
                                </p>
                                <p class="appointment-vet">
                                    Vet: <?php echo htmlspecialchars($appointment['vet_name']); ?>
                                </p>
                                <div class="appointment-actions">
                                    <a href="../appointments/view.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn">View Details</a>
                                    <button class="btn btn-danger" onclick="cancelAppointment(<?php echo $appointment['appointment_id']; ?>)">Cancel</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Vaccination Reminders -->
            <div class="card">
                <h2>Vaccination Reminders</h2>
                <?php if (empty($dueVaccinations)): ?>
                    <p>No upcoming vaccinations due.</p>
                <?php else: ?>
                    <div class="vaccination-list">
                        <?php foreach ($dueVaccinations as $vaccination): ?>
                            <div class="vaccination-item">
                                <p class="vaccination-pet">
                                    <?php echo htmlspecialchars($vaccination['pet_name']); ?>
                                </p>
                                <p class="vaccination-name">
                                    <?php echo htmlspecialchars($vaccination['vaccine_name']); ?>
                                </p>
                                <p class="vaccination-due">
                                    Due: <?php echo date('M d, Y', strtotime($vaccination['next_due_date'])); ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Notifications -->
            <div class="card">
                <h2>Recent Notifications</h2>
                <?php if (empty($notifications)): ?>
                    <p>No notifications.</p>
                <?php else: ?>
                    <div class="notification-list">
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?php echo $notification['read_at'] ? 'read' : 'unread'; ?>">
                                <p class="notification-message">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </p>
                                <p class="notification-time">
                                    <?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?>
                                </p>
                                <?php if (!$notification['read_at']): ?>
                                    <button class="btn btn-small" onclick="markNotificationRead(<?php echo $notification['notification_id']; ?>)">Mark as Read</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include('../includes/footer.php'); ?>
    
    <script>
    function cancelAppointment(appointmentId) {
        if (confirm('Are you sure you want to cancel this appointment?')) {
            fetch('../appointments/cancel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ appointment_id: appointmentId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to cancel appointment: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while cancelling the appointment');
            });
        }
    }

    function markNotificationRead(notificationId) {
        fetch('../notifications/mark_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ notification_id: notificationId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
    </script>
</body>
</html>