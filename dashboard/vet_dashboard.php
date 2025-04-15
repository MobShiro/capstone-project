<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a veterinarian
if (!isLoggedIn() || !hasRole('veterinarian')) {
    header('Location: ../index.php');
    exit;
}

// Get current user information
$user = getCurrentUser();
$conn = getDbConnection();

// Get vet_id from user_id
$vetStmt = $conn->prepare("SELECT * FROM vet_profiles WHERE user_id = ?");
$vetStmt->bind_param("i", $user['user_id']);
$vetStmt->execute();
$vetResult = $vetStmt->get_result();

$vetProfile = null;
if ($vetResult->num_rows === 1) {
    $vetProfile = $vetResult->fetch_assoc();
    $vetId = $vetProfile['vet_id'];
} else {
    // Redirect to complete vet profile if not set up
    header('Location: profile/complete_vet_profile.php');
    exit;
}

// Get today's appointments
$today = date('Y-m-d');
$todayStart = $today . ' 00:00:00';
$todayEnd = $today . ' 23:59:59';

$todayStmt = $conn->prepare("SELECT a.*, p.name as pet_name, p.species, p.breed, 
                           CONCAT(u.first_name, ' ', u.last_name) as owner_name
                           FROM appointments a
                           JOIN pets p ON a.pet_id = p.pet_id
                           JOIN users u ON p.owner_id = u.user_id
                           WHERE a.vet_id = ? 
                           AND a.appointment_date BETWEEN ? AND ?
                           AND a.status = 'Scheduled'
                           ORDER BY a.appointment_date ASC");
// Fix: Create variables for binding
$todayStmt->bind_param("iss", $vetId, $todayStart, $todayEnd);
$todayStmt->execute();
$todayResult = $todayStmt->get_result();

$todayAppointments = [];
while ($appointment = $todayResult->fetch_assoc()) {
    $todayAppointments[] = $appointment;
}

// Get upcoming appointments (next 7 days)
$nextWeekEnd = date('Y-m-d', strtotime('+7 days')) . ' 23:59:59';
$tomorrowStart = date('Y-m-d', strtotime('+1 day')) . ' 00:00:00';

$upcomingStmt = $conn->prepare("SELECT a.*, p.name as pet_name, p.species, p.breed, 
                              CONCAT(u.first_name, ' ', u.last_name) as owner_name
                              FROM appointments a
                              JOIN pets p ON a.pet_id = p.pet_id
                              JOIN users u ON p.owner_id = u.user_id
                              WHERE a.vet_id = ? 
                              AND a.appointment_date BETWEEN ? AND ?
                              AND a.status = 'Scheduled'
                              ORDER BY a.appointment_date ASC");
// Fix: Create variables for binding
$upcomingStmt->bind_param("iss", $vetId, $tomorrowStart, $nextWeekEnd);
$upcomingStmt->execute();
$upcomingResult = $upcomingStmt->get_result();

$upcomingAppointments = [];
while ($appointment = $upcomingResult->fetch_assoc()) {
    $upcomingAppointments[] = $appointment;
}

// Get recent medical records
$recentRecordsStmt = $conn->prepare("SELECT mr.*, p.name as pet_name, p.species, p.breed
                                   FROM medical_records mr
                                   JOIN pets p ON mr.pet_id = p.pet_id
                                   WHERE mr.vet_id = ?
                                   ORDER BY mr.record_date DESC
                                   LIMIT 5");
$recentRecordsStmt->bind_param("i", $vetId);
$recentRecordsStmt->execute();
$recentRecordsResult = $recentRecordsStmt->get_result();

$recentRecords = [];
while ($record = $recentRecordsResult->fetch_assoc()) {
    $recentRecords[] = $record;
}

// Get unread messages
$messagesStmt = $conn->prepare("SELECT m.*, 
                              CONCAT(u.first_name, ' ', u.last_name) as sender_name
                              FROM messages m
                              JOIN users u ON m.sender_id = u.user_id
                              WHERE m.receiver_id = ?
                              AND m.is_read = 0
                              ORDER BY m.sent_date DESC
                              LIMIT 5");
$messagesStmt->bind_param("i", $user['user_id']);
$messagesStmt->execute();
$messagesResult = $messagesStmt->get_result();

$unreadMessages = [];
while ($message = $messagesResult->fetch_assoc()) {
    $unreadMessages[] = $message;
}

// Get statistics
// Total patients
$patientStmt = $conn->prepare("SELECT COUNT(DISTINCT p.pet_id) as total_patients
                             FROM appointments a
                             JOIN pets p ON a.pet_id = p.pet_id
                             WHERE a.vet_id = ?");
$patientStmt->bind_param("i", $vetId);
$patientStmt->execute();
$patientResult = $patientStmt->get_result();
$patientData = $patientResult->fetch_assoc();
$totalPatients = $patientData['total_patients'];

// Total appointments this month
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t') . ' 23:59:59';

$monthApptStmt = $conn->prepare("SELECT COUNT(*) as month_appointments
                               FROM appointments
                               WHERE vet_id = ?
                               AND appointment_date BETWEEN ? AND ?");
$monthApptStmt->bind_param("iss", $vetId, $monthStart, $monthEnd);
$monthApptStmt->execute();
$monthApptResult = $monthApptStmt->get_result();
$monthApptData = $monthApptResult->fetch_assoc();
$monthAppointments = $monthApptData['month_appointments'];

// Total records this month
$monthRecordStmt = $conn->prepare("SELECT COUNT(*) as month_records
                                 FROM medical_records
                                 WHERE vet_id = ?
                                 AND record_date BETWEEN ? AND ?");
$monthRecordStmt->bind_param("iss", $vetId, $monthStart, $monthEnd);
$monthRecordStmt->execute();
$monthRecordResult = $monthRecordStmt->get_result();
$monthRecordData = $monthRecordResult->fetch_assoc();
$monthRecords = $monthRecordData['month_records'];

// Appointment distribution by type for this month
$apptTypesStmt = $conn->prepare("SELECT purpose, COUNT(*) as count
                               FROM appointments
                               WHERE vet_id = ?
                               AND appointment_date BETWEEN ? AND ?
                               GROUP BY purpose
                               ORDER BY count DESC");
$apptTypesStmt->bind_param("iss", $vetId, $monthStart, $monthEnd);
$apptTypesStmt->execute();
$apptTypesResult = $apptTypesStmt->get_result();

$appointmentTypes = [];
$appointmentTypeLabels = [];
$appointmentTypeCounts = [];

while ($type = $apptTypesResult->fetch_assoc()) {
    $appointmentTypes[] = $type;
    $appointmentTypeLabels[] = $type['purpose'];
    $appointmentTypeCounts[] = $type['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veterinarian Dashboard - Vet Anywhere</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <h1>Welcome, Dr. <?php echo $user['last_name']; ?>!</h1>
                    <p class="date"><?php echo date('l, F j, Y'); ?></p>
                </div>

                <!-- Dashboard Overview -->
                <div class="dashboard-cards">
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-paw"></i>
                        </div>
                        <div class="card-content">
                            <h3>Total Patients</h3>
                            <p class="card-value"><?php echo $totalPatients; ?></p>
                        </div>
                        <a href="patients/all_patients.php" class="card-link">View All</a>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="card-content">
                            <h3>Today's Appointments</h3>
                            <p class="card-value"><?php echo count($todayAppointments); ?></p>
                        </div>
                        <a href="appointments/upcoming.php" class="card-link">View All</a>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="card-content">
                            <h3>Records This Month</h3>
                            <p class="card-value"><?php echo $monthRecords; ?></p>
                        </div>
                        <a href="medical_records/index.php" class="card-link">View All</a>
                    </div>

                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="card-content">
                            <h3>Unread Messages</h3>
                            <p class="card-value"><?php echo count($unreadMessages); ?></p>
                        </div>
                        <a href="messages/inbox.php" class="card-link">View Inbox</a>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <h2>Quick Actions</h2>
                    <div class="action-buttons">
                        <a href="medical_records/create.php" class="action-btn">
                            <i class="fas fa-plus-circle"></i>
                            <span>New Medical Record</span>
                        </a>
                        <a href="schedule/availability.php" class="action-btn">
                            <i class="fas fa-clock"></i>
                            <span>Set Availability</span>
                        </a>
                        <a href="patients/search.php" class="action-btn">
                            <i class="fas fa-search"></i>
                            <span>Find Patient</span>
                        </a>
                        <a href="messages/compose.php" class="action-btn">
                            <i class="fas fa-paper-plane"></i>
                            <span>Send Message</span>
                        </a>
                    </div>
                </div>

                <!-- Sections Row -->
                <div class="dashboard-row">
                    <!-- Today's Appointments -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Today's Schedule</h2>
                            <a href="appointments/upcoming.php" class="view-all">View All</a>
                        </div>
                        
                        <?php if (empty($todayAppointments)): ?>
                            <div class="empty-state">
                                <img src="../assets/images/empty-appointments.svg" alt="No Appointments">
                                <p>No appointments scheduled for today</p>
                            </div>
                        <?php else: ?>
                            <div class="today-schedule">
                                <?php foreach ($todayAppointments as $appointment): ?>
                                    <div class="schedule-item">
                                        <div class="schedule-time">
                                            <?php echo date('g:i A', strtotime($appointment['appointment_date'])); ?>
                                        </div>
                                        <div class="schedule-content">
                                            <div class="schedule-dot"></div>
                                            <div class="schedule-details">
                                                <h4><?php echo htmlspecialchars($appointment['purpose']); ?></h4>
                                                <p>
                                                    <i class="fas fa-paw"></i> 
                                                    <?php echo htmlspecialchars($appointment['pet_name']); ?> 
                                                    (<?php echo htmlspecialchars($appointment['species']); ?>
                                                    <?php if ($appointment['breed']): ?>
                                                        - <?php echo htmlspecialchars($appointment['breed']); ?>
                                                    <?php endif; ?>)
                                                </p>
                                                <p>
                                                    <i class="fas fa-user"></i> 
                                                    <?php echo htmlspecialchars($appointment['owner_name']); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="schedule-actions">
                                            <a href="medical_records/create.php?appointment_id=<?php echo $appointment['appointment_id']; ?>" class="btn-primary btn-sm">
                                                Create Record
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Messages -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Recent Messages</h2>
                            <a href="messages/inbox.php" class="view-all">View All</a>
                        </div>
                        
                        <?php if (empty($unreadMessages)): ?>
                            <div class="empty-state">
                                <img src="../assets/images/empty-messages.svg" alt="No Messages">
                                <p>No unread messages</p>
                            </div>
                        <?php else: ?>
                            <div class="messages-list">
                                <?php foreach ($unreadMessages as $message): ?>
                                    <div class="message-card">
                                        <div class="message-header">
                                            <div class="message-sender">
                                                <i class="fas fa-user-circle"></i>
                                                <span><?php echo htmlspecialchars($message['sender_name']); ?></span>
                                            </div>
                                            <div class="message-date">
                                                <?php echo date('M d, g:i A', strtotime($message['sent_date'])); ?>
                                            </div>
                                        </div>
                                        <div class="message-subject">
                                            <?php echo htmlspecialchars($message['subject']); ?>
                                        </div>
                                        <div class="message-preview">
                                            <?php 
                                                $preview = substr($message['message_text'], 0, 100);
                                                echo htmlspecialchars($preview) . (strlen($message['message_text']) > 100 ? '...' : '');
                                            ?>
                                        </div>
                                        <div class="message-actions">
                                            <a href="messages/view.php?id=<?php echo $message['message_id']; ?>" class="btn-outline btn-sm">
                                                Read Message
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics Section -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Monthly Statistics</h2>
                    </div>
                    
                    <div class="statistics-grid">
                        <div class="stat-card">
                            <div class="stat-header">
                                <h3>Appointment Types</h3>
                            </div>
                            <div class="chart-container">
                                <canvas id="appointmentTypeChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-header">
                                <h3>Upcoming Appointments</h3>
                            </div>
                            
                            <?php if (empty($upcomingAppointments)): ?>
                                <div class="empty-state small">
                                    <p>No upcoming appointments</p>
                                </div>
                            <?php else: ?>
                                <div class="upcoming-list">
                                    <?php
                                        // Group appointments by date
                                        $groupedAppointments = [];
                                        foreach ($upcomingAppointments as $appt) {
                                            $date = date('Y-m-d', strtotime($appt['appointment_date']));
                                            if (!isset($groupedAppointments[$date])) {
                                                $groupedAppointments[$date] = [];
                                            }
                                            $groupedAppointments[$date][] = $appt;
                                        }
                                        
                                        // Display the next 5 days with appointments
                                        $counter = 0;
                                        foreach ($groupedAppointments as $date => $appts):
                                            if ($counter >= 5) break;
                                            $counter++;
                                    ?>
                                        <div class="upcoming-day">
                                            <div class="day-header">
                                                <span class="day-name"><?php echo date('l', strtotime($date)); ?></span>
                                                <span class="day-date"><?php echo date('M d', strtotime($date)); ?></span>
                                                <span class="appointment-count"><?php echo count($appts); ?> appointments</span>
                                            </div>
                                            <div class="day-appointments">
                                                <?php foreach ($appts as $index => $appt): ?>
                                                    <?php if ($index < 3): // Show max 3 appointments per day ?>
                                                        <div class="mini-appointment">
                                                            <span class="mini-time"><?php echo date('g:i A', strtotime($appt['appointment_date'])); ?></span>
                                                            <span class="mini-pet"><?php echo htmlspecialchars($appt['pet_name']); ?></span>
                                                            <span class="mini-type"><?php echo htmlspecialchars($appt['purpose']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                
                                                <?php if (count($appts) > 3): ?>
                                                    <div class="more-appointments">
                                                        <a href="appointments/upcoming.php">+<?php echo count($appts) - 3; ?> more</a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="stat-card recent-records">
                            <div class="stat-header">
                                <h3>Recent Medical Records</h3>
                                <a href="medical_records/index.php" class="view-all">View All</a>
                            </div>
                            
                            <?php if (empty($recentRecords)): ?>
                                <div class="empty-state small">
                                    <p>No recent records</p>
                                </div>
                            <?php else: ?>
                                <div class="recent-records-list">
                                    <?php foreach ($recentRecords as $record): ?>
                                        <div class="record-item">
                                            <div class="record-pet">
                                                <i class="fas fa-paw"></i>
                                                <span><?php echo htmlspecialchars($record['pet_name']); ?></span>
                                            </div>
                                            <div class="record-info">
                                                <span class="record-type"><?php echo htmlspecialchars($record['visit_type']); ?></span>
                                                <span class="record-date"><?php echo formatDate($record['record_date']); ?></span>
                                            </div>
                                            <div class="record-action">
                                                <a href="medical_records/view.php?id=<?php echo $record['record_id']; ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Appointment Type Chart
            const apptChartCtx = document.getElementById('appointmentTypeChart');
            if (apptChartCtx) {
                new Chart(apptChartCtx, {
                    type: 'doughnut',
                    data: {
                        labels: <?php echo json_encode($appointmentTypeLabels); ?>,
                        datasets: [{
                            data: <?php echo json_encode($appointmentTypeCounts); ?>,
                            backgroundColor: [
                                'rgba(74, 144, 226, 0.7)',
                                'rgba(106, 192, 69, 0.7)',
                                'rgba(243, 156, 18, 0.7)',
                                'rgba(231, 76, 60, 0.7)',
                                'rgba(155, 89, 182, 0.7)',
                                'rgba(52, 152, 219, 0.7)',
                                'rgba(46, 204, 113, 0.7)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    boxWidth: 12,
                                    padding: 15
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>