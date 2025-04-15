<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../../index.php');
    exit;
}

// Get current user information
$user = getCurrentUser();
$userType = $user['user_type'];

// Process appointment cancellation if requested
$statusMessage = '';
$statusType = '';

if (isset($_GET['success']) && $_GET['success'] == '1') {
    $statusMessage = "Appointment scheduled successfully!";
    $statusType = "success";
}

if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    $appointmentId = (int)$_GET['id'];
    $conn = getDbConnection();
    
    // Check if the appointment belongs to the user or their pet
    if ($userType === 'pet_owner') {
        $stmt = $conn->prepare("SELECT a.* FROM appointments a 
                              JOIN pets p ON a.pet_id = p.pet_id 
                              WHERE a.appointment_id = ? AND p.owner_id = ?");
        $stmt->bind_param("ii", $appointmentId, $user['user_id']);
    } else if ($userType === 'veterinarian') {
        // Get vet_id from user_id
        $vetQuery = $conn->prepare("SELECT vet_id FROM vet_profiles WHERE user_id = ?");
        $vetQuery->bind_param("i", $user['user_id']);
        $vetQuery->execute();
        $vetResult = $vetQuery->get_result();
        
        if ($vetResult->num_rows === 0) {
            $statusMessage = "Error: Veterinarian profile not found.";
            $statusType = "error";
        } else {
            $vetData = $vetResult->fetch_assoc();
            $vetId = $vetData['vet_id'];
            
            $stmt = $conn->prepare("SELECT * FROM appointments WHERE appointment_id = ? AND vet_id = ?");
            $stmt->bind_param("ii", $appointmentId, $vetId);
        }
    } else {
        $statusMessage = "Error: Unauthorized access.";
        $statusType = "error";
    }
    
    if (isset($stmt)) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            // Cancel the appointment
            $updateStmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled', updated_at = NOW() WHERE appointment_id = ?");
            $updateStmt->bind_param("i", $appointmentId);
            
            if ($updateStmt->execute()) {
                $statusMessage = "Appointment cancelled successfully.";
                $statusType = "success";
                
                // Log the cancellation
                logActivity($user['user_id'], 'Cancelled appointment', 'Appointment ID: ' . $appointmentId);
                
                // Get appointment details for notification
                $appointmentData = $result->fetch_assoc();
                $petId = $appointmentData['pet_id'];
                $appointmentDate = $appointmentData['appointment_date'];
                
                // If pet owner cancelled, notify vet
                if ($userType === 'pet_owner') {
                    $vetId = $appointmentData['vet_id'];
                    
                    // Get vet's user_id
                    $vetUserQuery = $conn->prepare("SELECT user_id FROM vet_profiles WHERE vet_id = ?");
                    $vetUserQuery->bind_param("i", $vetId);
                    $vetUserQuery->execute();
                    $vetUserResult = $vetUserQuery->get_result();
                    
                    if ($vetUserResult->num_rows === 1) {
                        $vetUserData = $vetUserResult->fetch_assoc();
                        $vetUserId = $vetUserData['user_id'];
                        
                        // Get pet name
                        $petQuery = $conn->prepare("SELECT name FROM pets WHERE pet_id = ?");
                        $petQuery->bind_param("i", $petId);
                        $petQuery->execute();
                        $petResult = $petQuery->get_result();
                        $petData = $petResult->fetch_assoc();
                        $petName = $petData['name'];
                        
                        // Create notification message
                        $messageSubject = "Appointment Cancelled";
                        $messageText = "The appointment scheduled for " . date('F j, Y \a\t g:i A', strtotime($appointmentDate)) . 
                                     " with " . $petName . " has been cancelled by the pet owner.";
                        
                        $msgStmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message_text, sent_date) 
                                                VALUES (?, ?, ?, ?, NOW())");
                        $msgStmt->bind_param("iiss", $user['user_id'], $vetUserId, $messageSubject, $messageText);
                        $msgStmt->execute();
                    }
                }
                // If vet cancelled, notify pet owner
                else if ($userType === 'veterinarian') {
                    // Get pet owner's user_id
                    $ownerQuery = $conn->prepare("SELECT p.owner_id FROM pets p WHERE p.pet_id = ?");
                    $ownerQuery->bind_param("i", $petId);
                    $ownerQuery->execute();
                    $ownerResult = $ownerQuery->get_result();
                    
                    if ($ownerResult->num_rows === 1) {
                        $ownerData = $ownerResult->fetch_assoc();
                        $ownerId = $ownerData['owner_id'];
                        
                        // Create notification message
                        $messageSubject = "Appointment Cancelled";
                        $messageText = "Your appointment scheduled for " . date('F j, Y \a\t g:i A', strtotime($appointmentDate)) . 
                                     " has been cancelled by the veterinarian. Please contact the clinic for more information or to reschedule.";
                        
                        $msgStmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message_text, sent_date) 
                                                VALUES (?, ?, ?, ?, NOW())");
                        $msgStmt->bind_param("iiss", $user['user_id'], $ownerId, $messageSubject, $messageText);
                        $msgStmt->execute();
                    }
                }
            } else {
                $statusMessage = "Error: Failed to cancel appointment.";
                $statusType = "error";
            }
        } else {
            $statusMessage = "Error: Appointment not found or access denied.";
            $statusType = "error";
        }
    }
}

// Get upcoming appointments based on user type
$conn = getDbConnection();
$appointments = [];

if ($userType === 'pet_owner') {
    // Get appointments for pet owner
    $stmt = $conn->prepare("SELECT a.*, p.name as pet_name, p.photo as pet_photo, 
                          CONCAT(u.first_name, ' ', u.last_name) as vet_name,
                          vp.clinic_name
                          FROM appointments a
                          JOIN pets p ON a.pet_id = p.pet_id
                          JOIN vet_profiles vp ON a.vet_id = vp.vet_id
                          JOIN users u ON vp.user_id = u.user_id
                          WHERE p.owner_id = ? 
                          ORDER BY a.appointment_date DESC");
    $stmt->bind_param("i", $user['user_id']);
} elseif ($userType === 'veterinarian') {
    // Get vet_id from user_id
    $vetIdQuery = "SELECT vet_id FROM vet_profiles WHERE user_id = ?";
    $vetStmt = $conn->prepare($vetIdQuery);
    $vetStmt->bind_param("i", $user['user_id']);
    $vetStmt->execute();
    $vetResult = $vetStmt->get_result();
    
    if ($vetResult->num_rows === 0) {
        $statusMessage = "Error: Veterinarian profile not found. Please complete your profile setup.";
        $statusType = "error";
    } else {
        $vetData = $vetResult->fetch_assoc();
        $vetId = $vetData['vet_id'];
        
        // Get appointments for veterinarian
        $stmt = $conn->prepare("SELECT a.*, p.name as pet_name, p.photo as pet_photo, 
                              CONCAT(u.first_name, ' ', u.last_name) as owner_name,
                              u.phone as owner_phone
                              FROM appointments a
                              JOIN pets p ON a.pet_id = p.pet_id
                              JOIN users u ON p.owner_id = u.user_id
                              WHERE a.vet_id = ? 
                              ORDER BY a.appointment_date DESC");
        $stmt->bind_param("i", $vetId);
    }
} else {
    header('Location: ../../index.php');
    exit;
}

if (isset($stmt)) {
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($appointment = $result->fetch_assoc()) {
        $appointments[] = $appointment;
    }
}

// Separate appointments by status
$upcomingAppointments = [];
$pastAppointments = [];
$cancelledAppointments = [];

$currentDate = date('Y-m-d H:i:s');

foreach ($appointments as $appointment) {
    if ($appointment['status'] === 'Cancelled') {
        $cancelledAppointments[] = $appointment;
    } elseif ($appointment['appointment_date'] > $currentDate && $appointment['status'] === 'Scheduled') {
        $upcomingAppointments[] = $appointment;
    } else {
        $pastAppointments[] = $appointment;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - Vet Anywhere</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <?php include '../includes/header.php'; ?>

            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <div class="content-header">
                    <h1>Appointments</h1>
                    <nav class="breadcrumb">
                        <a href="../<?php echo $userType === 'pet_owner' ? 'owner_dashboard.php' : 'vet_dashboard.php'; ?>">Dashboard</a> /
                        <span>Appointments</span>
                    </nav>
                </div>

                <!-- Status Message (if any) -->
                <?php if ($statusMessage): ?>
                    <div class="alert alert-<?php echo $statusType === 'error' ? 'danger' : $statusType; ?>">
                        <?php echo $statusMessage; ?>
                    </div>
                <?php endif; ?>

                <!-- Appointment Actions -->
                <div class="section-header-with-actions">
                    <h2>My Appointments</h2>
                    <?php if ($userType === 'pet_owner'): ?>
                        <a href="schedule.php" class="btn-primary">
                            <i class="fas fa-calendar-plus"></i> Schedule Appointment
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Appointment Filters -->
                <div class="filters-bar">
                    <div class="tab-filters">
                        <button class="filter-btn active" data-filter="upcoming">Upcoming (<?php echo count($upcomingAppointments); ?>)</button>
                        <button class="filter-btn" data-filter="past">Past (<?php echo count($pastAppointments); ?>)</button>
                        <button class="filter-btn" data-filter="cancelled">Cancelled (<?php echo count($cancelledAppointments); ?>)</button>
                    </div>
                    
                    <div class="search-filter">
                        <input type="text" id="appointmentSearch" placeholder="Search appointments...">
                        <i class="fas fa-search"></i>
                    </div>
                </div>

                <!-- Upcoming Appointments Section -->
                <div class="appointments-section filter-section" id="upcoming-section">
                    <?php if (empty($upcomingAppointments)): ?>
                        <div class="empty-state">
                            <img src="../../assets/images/empty-appointments.svg" alt="No Appointments">
                            <p>No upcoming appointments scheduled</p>
                            <?php if ($userType === 'pet_owner'): ?>
                                <a href="schedule.php" class="btn-primary">Schedule Now</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="appointments-list">
                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                <div class="appointment-card detailed" data-search="<?php echo strtolower($appointment['pet_name'] . ' ' . ($userType === 'pet_owner' ? $appointment['vet_name'] : $appointment['owner_name']) . ' ' . $appointment['purpose']); ?>">
                                    <div class="appointment-date">
                                        <?php 
                                            $date = new DateTime($appointment['appointment_date']);
                                            echo '<div class="date-day">' . $date->format('d') . '</div>';
                                            echo '<div class="date-month">' . $date->format('M') . '</div>';
                                            echo '<div class="date-year">' . $date->format('Y') . '</div>';
                                        ?>
                                    </div>
                                    
                                    <div class="appointment-pet">
                                        <?php if ($appointment['pet_photo']): ?>
                                            <img src="../../uploads/pets/<?php echo $appointment['pet_photo']; ?>" alt="<?php echo htmlspecialchars($appointment['pet_name']); ?>">
                                        <?php else: ?>
                                            <img src="../../assets/images/default-pet.png" alt="Default Pet Image">
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($appointment['pet_name']); ?></span>
                                    </div>
                                    
                                    <div class="appointment-details">
                                        <h4><?php echo htmlspecialchars($appointment['purpose']); ?></h4>
                                        <p>
                                            <i class="fas fa-clock"></i> 
                                            <?php echo $date->format('g:i A'); ?>
                                        </p>
                                        
                                        <?php if ($userType === 'pet_owner'): ?>
                                            <p>
                                                <i class="fas fa-user-md"></i> 
                                                Dr. <?php echo htmlspecialchars($appointment['vet_name']); ?>
                                            </p>
                                            <?php if (!empty($appointment['clinic_name'])): ?>
                                                <p>
                                                    <i class="fas fa-clinic-medical"></i> 
                                                    <?php echo htmlspecialchars($appointment['clinic_name']); ?>
                                                </p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <p>
                                                <i class="fas fa-user"></i> 
                                                <?php echo htmlspecialchars($appointment['owner_name']); ?>
                                            </p>
                                            <?php if (!empty($appointment['owner_phone'])): ?>
                                                <p>
                                                    <i class="fas fa-phone"></i> 
                                                    <?php echo htmlspecialchars($appointment['owner_phone']); ?>
                                                </p>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($appointment['notes'])): ?>
                                            <div class="appointment-notes">
                                                <p><strong>Notes:</strong> <?php echo htmlspecialchars($appointment['notes']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="appointment-status">
                                        <span class="status-badge <?php echo strtolower($appointment['status']); ?>">
                                            <?php echo $appointment['status']; ?>
                                        </span>
                                        
                                        <div class="countdown">
                                            <?php
                                                $appointmentTime = new DateTime($appointment['appointment_date']);
                                                $now = new DateTime();
                                                $interval = $now->diff($appointmentTime);
                                                
                                                if ($interval->days > 0) {
                                                    echo '<span>' . $interval->format('%a days') . '</span>';
                                                } else {
                                                    $hours = $interval->h + ($interval->days * 24);
                                                    echo '<span>' . $hours . ' hours</span>';
                                                }
                                            ?>
                                            <span>remaining</span>
                                        </div>
                                    </div>
                                    
                                    <div class="appointment-actions">
                                        <?php if ($userType === 'veterinarian'): ?>
                                            <a href="../medical_records/create.php?pet_id=<?php echo $appointment['pet_id']; ?>" class="btn-primary">
                                                <i class="fas fa-clipboard-list"></i> Create Record
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="appointment_details.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn-outline">
                                            <i class="fas fa-eye"></i> Details
                                        </a>
                                        
                                        <button class="btn-danger cancel-appointment" data-id="<?php echo $appointment['appointment_id']; ?>">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Past Appointments Section -->
                <div class="appointments-section filter-section" id="past-section" style="display: none;">
                    <?php if (empty($pastAppointments)): ?>
                        <div class="empty-state">
                            <img src="../../assets/images/empty-history.svg" alt="No Past Appointments">
                            <p>No past appointment history found</p>
                        </div>
                    <?php else: ?>
                        <div class="appointments-list">
                            <?php foreach ($pastAppointments as $appointment): ?>
                                <div class="appointment-card detailed past" data-search="<?php echo strtolower($appointment['pet_name'] . ' ' . ($userType === 'pet_owner' ? $appointment['vet_name'] : $appointment['owner_name']) . ' ' . $appointment['purpose']); ?>">
                                    <div class="appointment-date">
                                        <?php 
                                            $date = new DateTime($appointment['appointment_date']);
                                            echo '<div class="date-day">' . $date->format('d') . '</div>';
                                            echo '<div class="date-month">' . $date->format('M') . '</div>';
                                            echo '<div class="date-year">' . $date->format('Y') . '</div>';
                                        ?>
                                    </div>
                                    
                                    <div class="appointment-pet">
                                        <?php if ($appointment['pet_photo']): ?>
                                            <img src="../../uploads/pets/<?php echo $appointment['pet_photo']; ?>" alt="<?php echo htmlspecialchars($appointment['pet_name']); ?>">
                                        <?php else: ?>
                                            <img src="../../assets/images/default-pet.png" alt="Default Pet Image">
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($appointment['pet_name']); ?></span>
                                    </div>
                                    
                                    <div class="appointment-details">
                                        <h4><?php echo htmlspecialchars($appointment['purpose']); ?></h4>
                                        <p>
                                            <i class="fas fa-clock"></i> 
                                            <?php echo $date->format('g:i A'); ?>
                                        </p>
                                        
                                        <?php if ($userType === 'pet_owner'): ?>
                                            <p>
                                                <i class="fas fa-user-md"></i> 
                                                Dr. <?php echo htmlspecialchars($appointment['vet_name']); ?>
                                            </p>
                                        <?php else: ?>
                                            <p>
                                                <i class="fas fa-user"></i> 
                                                <?php echo htmlspecialchars($appointment['owner_name']); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($appointment['notes'])): ?>
                                            <div class="appointment-notes">
                                                <p><strong>Notes:</strong> <?php echo htmlspecialchars($appointment['notes']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="appointment-status">
                                        <span class="status-badge <?php echo strtolower($appointment['status']); ?>">
                                            <?php echo $appointment['status']; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="appointment-actions">
                                        <a href="appointment_details.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn-outline">
                                            <i class="fas fa-eye"></i> Details
                                        </a>
                                        
                                        <?php if ($userType === 'pet_owner'): ?>
                                            <a href="schedule.php?reschedule=<?php echo $appointment['appointment_id']; ?>" class="btn-primary">
                                                <i class="fas fa-redo"></i> Reschedule
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Cancelled Appointments Section -->
                <div class="appointments-section filter-section" id="cancelled-section" style="display: none;">
                    <?php if (empty($cancelledAppointments)): ?>
                        <div class="empty-state">
                            <img src="../../assets/images/empty-cancelled.svg" alt="No Cancelled Appointments">
                            <p>No cancelled appointments found</p>
                        </div>
                    <?php else: ?>
                        <div class="appointments-list">
                            <?php foreach ($cancelledAppointments as $appointment): ?>
                                <div class="appointment-card detailed cancelled" data-search="<?php echo strtolower($appointment['pet_name'] . ' ' . ($userType === 'pet_owner' ? $appointment['vet_name'] : $appointment['owner_name']) . ' ' . $appointment['purpose']); ?>">
                                    <div class="appointment-date">
                                        <?php 
                                            $date = new DateTime($appointment['appointment_date']);
                                            echo '<div class="date-day">' . $date->format('d') . '</div>';
                                            echo '<div class="date-month">' . $date->format('M') . '</div>';
                                            echo '<div class="date-year">' . $date->format('Y') . '</div>';
                                        ?>
                                    </div>
                                    
                                    <div class="appointment-pet">
                                        <?php if ($appointment['pet_photo']): ?>
                                            <img src="../../uploads/pets/<?php echo $appointment['pet_photo']; ?>" alt="<?php echo htmlspecialchars($appointment['pet_name']); ?>">
                                        <?php else: ?>
                                            <img src="../../assets/images/default-pet.png" alt="Default Pet Image">
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($appointment['pet_name']); ?></span>
                                    </div>
                                    
                                    <div class="appointment-details">
                                        <h4><?php echo htmlspecialchars($appointment['purpose']); ?></h4>
                                        <p>
                                            <i class="fas fa-clock"></i> 
                                            <?php echo $date->format('g:i A'); ?>
                                        </p>
                                        
                                        <?php if ($userType === 'pet_owner'): ?>
                                            <p>
                                                <i class="fas fa-user-md"></i> 
                                                Dr. <?php echo htmlspecialchars($appointment['vet_name']); ?>
                                            </p>
                                        <?php else: ?>
                                            <p>
                                                <i class="fas fa-user"></i> 
                                                <?php echo htmlspecialchars($appointment['owner_name']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="appointment-status">
                                        <span class="status-badge cancelled">
                                            Cancelled
                                        </span>
                                        
                                        <p class="cancelled-date">
                                            <?php 
                                                echo 'Cancelled on ' . 
                                                    date('M d, Y', strtotime($appointment['updated_at']));
                                            ?>
                                        </p>
                                    </div>
                                    
                                    <div class="appointment-actions">
                                        <?php if ($userType === 'pet_owner'): ?>
                                            <a href="schedule.php?reschedule=<?php echo $appointment['appointment_id']; ?>" class="btn-primary">
                                                <i class="fas fa-redo"></i> Reschedule
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancellation Confirmation Modal -->
    <div class="modal" id="cancelModal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Cancel Appointment</h2>
            <p>Are you sure you want to cancel this appointment?</p>
            <p class="modal-warning">This action cannot be undone.</p>
            <div class="modal-actions">
                <button class="btn-outline" id="cancelModalNo">No, Keep It</button>
                <a href="#" class="btn-danger" id="cancelModalYes">Yes, Cancel It</a>
            </div>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab filters
            const filterBtns = document.querySelectorAll('.filter-btn');
            const filterSections = document.querySelectorAll('.filter-section');
            
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');
                    
                    // Deactivate all buttons and hide all sections
                    filterBtns.forEach(b => b.classList.remove('active'));
                    filterSections.forEach(s => s.style.display = 'none');
                    
                    // Activate selected button and show corresponding section
                    this.classList.add('active');
                    document.getElementById(filter + '-section').style.display = 'block';
                });
            });
            
            // Search functionality
            const searchInput = document.getElementById('appointmentSearch');
            const appointmentCards = document.querySelectorAll('.appointment-card');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    
                    appointmentCards.forEach(card => {
                        const searchData = card.getAttribute('data-search').toLowerCase();
                        
                        if (searchData.includes(searchTerm)) {
                            card.style.display = 'flex';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            }
            
            // Cancel appointment modal
            const cancelBtns = document.querySelectorAll('.cancel-appointment');
            const cancelModal = document.getElementById('cancelModal');
            const cancelModalNo = document.getElementById('cancelModalNo');
            const cancelModalYes = document.getElementById('cancelModalYes');
            const modalClose = document.querySelector('#cancelModal .close');
            
            let appointmentIdToCancel = null;
            
            cancelBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    appointmentIdToCancel = this.getAttribute('data-id');
                    cancelModalYes.href = `?action=cancel&id=${appointmentIdToCancel}`;
                    cancelModal.style.display = 'block';
                });
            });
            
            if (modalClose) {
                modalClose.addEventListener('click', function() {
                    cancelModal.style.display = 'none';
                });
            }
            
            if (cancelModalNo) {
                cancelModalNo.addEventListener('click', function() {
                    cancelModal.style.display = 'none';
                });
            }
            
            // Close modal when clicking outside
            window.addEventListener('click', function(e) {
                if (e.target === cancelModal) {
                    cancelModal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>