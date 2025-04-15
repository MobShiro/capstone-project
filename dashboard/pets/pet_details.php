<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../../index.php');
    exit;
}

// Check if pet ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: my_pets.php');
    exit;
}

$petId = (int)$_GET['id'];
$user = getCurrentUser();
$conn = getDbConnection();

// Get pet details
if ($user['user_type'] === 'pet_owner') {
    // For pet owners, only show their own pets
    $stmt = $conn->prepare("SELECT * FROM pets WHERE pet_id = ? AND owner_id = ?");
    $stmt->bind_param("ii", $petId, $user['user_id']);
} else if ($user['user_type'] === 'veterinarian') {
    // For vets, they can see any pet
    $stmt = $conn->prepare("SELECT * FROM pets WHERE pet_id = ?");
    $stmt->bind_param("i", $petId);
} else {
    header('Location: ../../index.php');
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Pet not found or not owned by the user
    header('Location: my_pets.php');
    exit;
}

$pet = $result->fetch_assoc();

// Get owner information
$ownerStmt = $conn->prepare("SELECT user_id, first_name, last_name, email, phone 
                          FROM users 
                          WHERE user_id = ?");
$ownerStmt->bind_param("i", $pet['owner_id']);
$ownerStmt->execute();
$ownerResult = $ownerStmt->get_result();
$owner = $ownerResult->fetch_assoc();

// Get medical records
$recordsStmt = $conn->prepare("SELECT mr.*, 
                             CONCAT(u.first_name, ' ', u.last_name) as vet_name
                             FROM medical_records mr
                             LEFT JOIN vet_profiles vp ON mr.vet_id = vp.vet_id
                             LEFT JOIN users u ON vp.user_id = u.user_id
                             WHERE mr.pet_id = ?
                             ORDER BY mr.record_date DESC");
$recordsStmt->bind_param("i", $petId);
$recordsStmt->execute();
$recordsResult = $recordsStmt->get_result();
$medicalRecords = [];
while ($record = $recordsResult->fetch_assoc()) {
    $medicalRecords[] = $record;
}

// Get vaccinations
$vaccStmt = $conn->prepare("SELECT v.*, 
                          CONCAT(u.first_name, ' ', u.last_name) as vet_name
                          FROM vaccinations v
                          LEFT JOIN vet_profiles vp ON v.administered_by = vp.vet_id
                          LEFT JOIN users u ON vp.user_id = u.user_id
                          WHERE v.pet_id = ?
                          ORDER BY v.administered_date DESC");
$vaccStmt->bind_param("i", $petId);
$vaccStmt->execute();
$vaccResult = $vaccStmt->get_result();
$vaccinations = [];
while ($vacc = $vaccResult->fetch_assoc()) {
    $vaccinations[] = $vacc;
}

// Get medications
$medStmt = $conn->prepare("SELECT m.*, 
                         CONCAT(u.first_name, ' ', u.last_name) as vet_name
                         FROM medications m
                         LEFT JOIN vet_profiles vp ON m.prescribed_by = vp.vet_id
                         LEFT JOIN users u ON vp.user_id = u.user_id
                         WHERE m.pet_id = ?
                         ORDER BY m.start_date DESC");
$medStmt->bind_param("i", $petId);
$medStmt->execute();
$medResult = $medStmt->get_result();
$medications = [];
while ($med = $medResult->fetch_assoc()) {
    $medications[] = $med;
}

// Get upcoming appointments
$apptStmt = $conn->prepare("SELECT a.*, 
                          CONCAT(u.first_name, ' ', u.last_name) as vet_name
                          FROM appointments a
                          JOIN vet_profiles vp ON a.vet_id = vp.vet_id
                          JOIN users u ON vp.user_id = u.user_id
                          WHERE a.pet_id = ? AND a.status = 'Scheduled'
                          ORDER BY a.appointment_date ASC");
$apptStmt->bind_param("i", $petId);
$apptStmt->execute();
$apptResult = $apptStmt->get_result();
$appointments = [];
while ($appt = $apptResult->fetch_assoc()) {
    $appointments[] = $appt;
}

// Get health metrics for weight chart
$metricsStmt = $conn->prepare("SELECT recorded_date, weight 
                             FROM health_metrics 
                             WHERE pet_id = ? AND weight IS NOT NULL
                             ORDER BY recorded_date ASC
                             LIMIT 10");
$metricsStmt->bind_param("i", $petId);
$metricsStmt->execute();
$metricsResult = $metricsStmt->get_result();
$weightDates = [];
$weightValues = [];
while ($metric = $metricsResult->fetch_assoc()) {
    $weightDates[] = formatDate($metric['recorded_date'], 'M d');
    $weightValues[] = $metric['weight'];
}

// Get allergies and conditions
$conditionsStmt = $conn->prepare("SELECT ac.*, 
                                CONCAT(u.first_name, ' ', u.last_name) as diagnosed_by_name
                                FROM allergies_conditions ac
                                LEFT JOIN vet_profiles vp ON ac.diagnosed_by = vp.vet_id
                                LEFT JOIN users u ON vp.user_id = u.user_id
                                WHERE ac.pet_id = ?
                                ORDER BY ac.diagnosed_date DESC");
$conditionsStmt->bind_param("i", $petId);
$conditionsStmt->execute();
$conditionsResult = $conditionsStmt->get_result();
$conditions = [];
while ($condition = $conditionsResult->fetch_assoc()) {
    $conditions[] = $condition;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pet['name']); ?> - Pet Details - Vet Anywhere</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <h1><?php echo htmlspecialchars($pet['name']); ?></h1>
                    <nav class="breadcrumb">
                        <a href="../owner_dashboard.php">Dashboard</a> /
                        <a href="my_pets.php">My Pets</a> /
                        <span><?php echo htmlspecialchars($pet['name']); ?></span>
                    </nav>
                </div>

                <!-- Pet Profile Header -->
                <div class="pet-profile-header">
                    <div class="pet-profile-image">
                        <?php if ($pet['photo']): ?>
                            <img src="../../uploads/pets/<?php echo $pet['photo']; ?>" alt="<?php echo htmlspecialchars($pet['name']); ?>">
                        <?php else: ?>
                            <img src="../../assets/images/default-pet.png" alt="Default Pet Image">
                        <?php endif; ?>
                    </div>
                    <div class="pet-profile-details">
                        <h2><?php echo htmlspecialchars($pet['name']); ?></h2>
                        <div class="pet-details-grid">
                            <div class="pet-detail">
                                <span class="detail-label">Species:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($pet['species']); ?></span>
                            </div>
                            <div class="pet-detail">
                                <span class="detail-label">Breed:</span>
                                <span class="detail-value"><?php echo $pet['breed'] ? htmlspecialchars($pet['breed']) : 'Not specified'; ?></span>
                            </div>
                            <div class="pet-detail">
                                <span class="detail-label">Gender:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($pet['gender']); ?></span>
                            </div>
                            <div class="pet-detail">
                                <span class="detail-label">Age:</span>
                                <span class="detail-value">
                                    <?php 
                                        echo $pet['date_of_birth'] 
                                            ? calculateAge($pet['date_of_birth']) 
                                            : 'Unknown'; 
                                    ?>
                                </span>
                            </div>
                            <div class="pet-detail">
                                <span class="detail-label">Weight:</span>
                                <span class="detail-value">
                                    <?php 
                                        echo $pet['weight'] 
                                            ? htmlspecialchars($pet['weight']) . ' kg' 
                                            : 'Not recorded'; 
                                    ?>
                                </span>
                            </div>
                            <div class="pet-detail">
                                <span class="detail-label">Color:</span>
                                <span class="detail-value">
                                    <?php echo $pet['color'] ? htmlspecialchars($pet['color']) : 'Not specified'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="pet-profile-actions">
                        <a href="edit_pet.php?id=<?php echo $pet['pet_id']; ?>" class="btn-outline">
                            <i class="fas fa-edit"></i> Edit Pet
                        </a>
                        <?php if ($user['user_type'] === 'pet_owner'): ?>
                            <a href="../appointments/schedule.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn-primary">
                                <i class="fas fa-calendar-plus"></i> Schedule Appointment
                            </a>
                        <?php elseif ($user['user_type'] === 'veterinarian'): ?>
                            <a href="../medical_records/create.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn-primary">
                                <i class="fas fa-plus-circle"></i> Add Medical Record
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Info Cards -->
                <div class="quick-info-cards">
                    <div class="info-card">
                        <div class="info-icon vaccination-icon">
                            <i class="fas fa-syringe"></i>
                        </div>
                        <div class="info-content">
                            <h3>Vaccinations</h3>
                            <p class="info-number"><?php echo count($vaccinations); ?></p>
                            <?php
                                $dueVaccines = 0;
                                foreach ($vaccinations as $vac) {
                                    if (!empty($vac['next_due_date']) && strtotime($vac['next_due_date']) <= strtotime('+30 days')) {
                                        $dueVaccines++;
                                    }
                                }
                                if ($dueVaccines > 0): 
                            ?>
                                <p class="info-alert"><?php echo $dueVaccines; ?> due soon</p>
                            <?php endif; ?>
                        </div>
                        <a href="#vaccinations" class="info-link">View All</a>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-icon appointments-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="info-content">
                            <h3>Appointments</h3>
                            <p class="info-number"><?php echo count($appointments); ?></p>
                            <?php if (!empty($appointments)): ?>
                                <p class="info-alert">
                                    Next: <?php echo formatDate($appointments[0]['appointment_date'], 'M d, Y'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <a href="#appointments" class="info-link">View All</a>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-icon medications-icon">
                            <i class="fas fa-pills"></i>
                        </div>
                        <div class="info-content">
                            <h3>Medications</h3>
                            <p class="info-number"><?php echo count($medications); ?></p>
                            <?php
                                $activeMeds = 0;
                                foreach ($medications as $med) {
                                    if (empty($med['end_date']) || strtotime($med['end_date']) >= time()) {
                                        $activeMeds++;
                                    }
                                }
                                if ($activeMeds > 0): 
                            ?>
                                <p class="info-alert"><?php echo $activeMeds; ?> active</p>
                            <?php endif; ?>
                        </div>
                        <a href="#medications" class="info-link">View All</a>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-icon records-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="info-content">
                            <h3>Medical Records</h3>
                            <p class="info-number"><?php echo count($medicalRecords); ?></p>
                            <?php if (!empty($medicalRecords)): ?>
                                <p class="info-alert">
                                    Last: <?php echo formatDate($medicalRecords[0]['record_date'], 'M d, Y'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <a href="#medical-records" class="info-link">View All</a>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <div class="tab-navigation">
                    <div class="tab-links">
                        <a href="#" class="tab-link active" data-tab="overview">Overview</a>
                        <a href="#" class="tab-link" data-tab="medical-records">Medical Records</a>
                        <a href="#" class="tab-link" data-tab="vaccinations">Vaccinations</a>
                        <a href="#" class="tab-link" data-tab="medications">Medications</a>
                        <a href="#" class="tab-link" data-tab="appointments">Appointments</a>
                        <a href="#" class="tab-link" data-tab="health-metrics">Health Metrics</a>
                    </div>
                </div>

                <!-- Tab Contents -->
                <div class="tab-contents">
                    <!-- Overview Tab -->
                    <div id="overview" class="tab-content active">
                        <div class="overview-grid">
                            <!-- Important Info Section -->
                            <div class="overview-card">
                                <h3>Important Information</h3>
                                
                                <?php if (!empty($conditions)): ?>
                                    <div class="conditions-list">
                                        <h4>Allergies & Conditions</h4>
                                        <?php foreach ($conditions as $condition): ?>
                                            <div class="condition-badge <?php echo strtolower($condition['severity']); ?>">
                                                <?php echo htmlspecialchars($condition['name']); ?> 
                                                <span class="condition-type">(<?php echo $condition['type']; ?>)</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p>No known allergies or medical conditions.</p>
                                <?php endif; ?>
                                
                                <div class="pet-additional-info">
                                    <div class="info-item">
                                        <span class="info-label">Microchip Number:</span>
                                        <span class="info-value">
                                            <?php echo $pet['microchip_number'] ? htmlspecialchars($pet['microchip_number']) : 'Not recorded'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="info-item">
                                        <span class="info-label">Registration Date:</span>
                                        <span class="info-value">
                                            <?php echo formatDate($pet['registered_date']); ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($pet['notes'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Notes:</span>
                                            <span class="info-value">
                                                <?php echo nl2br(htmlspecialchars($pet['notes'])); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($user['user_type'] === 'veterinarian'): ?>
                                    <div class="owner-info">
                                        <h4>Owner Information</h4>
                                        <p>
                                            <strong>Name:</strong> <?php echo htmlspecialchars($owner['first_name'] . ' ' . $owner['last_name']); ?><br>
                                            <strong>Email:</strong> <?php echo htmlspecialchars($owner['email']); ?><br>
                                            <strong>Phone:</strong> <?php echo $owner['phone'] ? htmlspecialchars($owner['phone']) : 'Not provided'; ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="button-group">
                                    <a href="add_condition.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn-outline btn-sm">
                                        <i class="fas fa-plus"></i> Add Condition
                                    </a>
                                    <?php if ($user['user_type'] === 'pet_owner'): ?>
                                        <button class="btn-danger btn-sm" id="reportLostBtn">
                                            <i class="fas fa-exclamation-triangle"></i> Report Lost
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Weight Chart -->
                            <div class="overview-card">
                                <h3>Weight History</h3>
                                <?php if (!empty($weightValues)): ?>
                                    <div class="chart-container">
                                        <canvas id="weightChart" 
                                                data-values='<?php echo json_encode($weightValues); ?>' 
                                                data-labels='<?php echo json_encode($weightDates); ?>'></canvas>
                                    </div>
                                <?php else: ?>
                                    <div class="empty-chart">
                                        <p>No weight data recorded yet.</p>
                                        <a href="../health_metrics/add.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn-outline btn-sm">
                                            <i class="fas fa-plus"></i> Add Weight Record
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Upcoming Events -->
                            <div class="overview-card">
                                <h3>Upcoming Events</h3>
                                <?php if (empty($appointments) && empty($dueVaccines)): ?>
                                    <div class="empty-events">
                                        <p>No upcoming events scheduled.</p>
                                        <?php if ($user['user_type'] === 'pet_owner'): ?>
                                            <a href="../appointments/schedule.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn-outline btn-sm">
                                                <i class="fas fa-calendar-plus"></i> Schedule Appointment
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="timeline">
                                        <?php 
                                        $events = [];
                                        
                                        // Add appointments to events
                                        foreach ($appointments as $appointment) {
                                            $events[] = [
                                                'date' => $appointment['appointment_date'],
                                                'type' => 'appointment',
                                                'title' => $appointment['purpose'],
                                                'details' => 'Appointment with Dr. ' . $appointment['vet_name'],
                                                'id' => $appointment['appointment_id']
                                            ];
                                        }
                                        
                                        // Add due vaccinations to events
                                        foreach ($vaccinations as $vaccination) {
                                            if (!empty($vaccination['next_due_date']) && strtotime($vaccination['next_due_date']) <= strtotime('+30 days')) {
                                                $events[] = [
                                                    'date' => $vaccination['next_due_date'] . ' 09:00:00', // Default to 9 AM
                                                    'type' => 'vaccination',
                                                    'title' => $vaccination['vaccine_name'] . ' Due',
                                                    'details' => 'Vaccination due on ' . formatDate($vaccination['next_due_date']),
                                                    'id' => $vaccination['vaccination_id']
                                                ];
                                            }
                                        }
                                        
                                        // Sort events by date
                                        usort($events, function($a, $b) {
                                            return strtotime($a['date']) - strtotime($b['date']);
                                        });
                                        
                                        // Display the next 5 events
                                        $displayedEvents = array_slice($events, 0, 5);
                                        
                                        foreach ($displayedEvents as $event):
                                            $eventDate = new DateTime($event['date']);
                                            $today = new DateTime();
                                            $interval = $today->diff($eventDate);
                                            $daysRemaining = $interval->days;
                                            $isPast = $today > $eventDate;
                                        ?>
                                            <div class="timeline-item <?php echo $isPast ? 'past' : ''; ?>">
                                                <div class="timeline-marker <?php echo $event['type']; ?>">
                                                    <?php if ($event['type'] === 'appointment'): ?>
                                                        <i class="fas fa-calendar-check"></i>
                                                    <?php elseif ($event['type'] === 'vaccination'): ?>
                                                        <i class="fas fa-syringe"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="timeline-content">
                                                    <h4><?php echo htmlspecialchars($event['title']); ?></h4>
                                                    <p class="timeline-date">
                                                        <?php 
                                                            echo formatDate($event['date'], 'M d, Y - g:i A');
                                                            if (!$isPast) {
                                                                echo ' <span class="days-remaining">(' . 
                                                                    ($daysRemaining == 0 ? 'Today' : 
                                                                    ($daysRemaining == 1 ? 'Tomorrow' : 
                                                                    'in ' . $daysRemaining . ' days')) . 
                                                                    ')</span>';
                                                            }
                                                        ?>
                                                    </p>
                                                    <p><?php echo htmlspecialchars($event['details']); ?></p>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Recent Medical Events -->
                            <div class="overview-card">
                                <h3>Recent Medical Events</h3>
                                <?php if (empty($medicalRecords)): ?>
                                    <div class="empty-records">
                                        <p>No medical records added yet.</p>
                                        <?php if ($user['user_type'] === 'veterinarian'): ?>
                                            <a href="../medical_records/create.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn-outline btn-sm">
                                                <i class="fas fa-plus"></i> Add Medical Record
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <?php 
                                    // Get only the 3 most recent records
                                    $recentRecords = array_slice($medicalRecords, 0, 3); 
                                    foreach ($recentRecords as $record): 
                                    ?>
                                        <div class="record-summary">
                                            <div class="record-date">
                                                <span class="date"><?php echo formatDate($record['record_date'], 'M d'); ?></span>
                                                <span class="year"><?php echo formatDate($record['record_date'], 'Y'); ?></span>
                                            </div>
                                            <div class="record-info">
                                                <h4><?php echo htmlspecialchars($record['visit_type']); ?></h4>
                                                <p>
                                                    <strong>Vet:</strong> 
                                                    <?php echo $record['vet_name'] ? 'Dr. ' . htmlspecialchars($record['vet_name']) : 'Not specified'; ?>
                                                </p>
                                                <?php if (!empty($record['diagnosis'])): ?>
                                                    <p class="diagnosis">
                                                        <strong>Diagnosis:</strong> 
                                                        <?php echo htmlspecialchars($record['diagnosis']); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <?php if (!empty($record['follow_up_date'])): ?>
                                                    <p>
                                                        <strong>Follow-up:</strong> 
                                                        <?php echo formatDate($record['follow_up_date']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="record-actions">
                                                <a href="../medical_records/view.php?id=<?php echo $record['record_id']; ?>" class="btn-outline btn-sm">
                                                    View Details
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($medicalRecords) > 3): ?>
                                        <div class="view-more-link">
                                            <a href="#medical-records" class="tab-link-trigger" data-tab="medical-records">
                                                View all <?php echo count($medicalRecords); ?> medical records
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Medical Records Tab -->
                    <div id="medical-records" class="tab-content">
                        <div class="section-actions">
                            <?php if ($user['user_type'] === 'veterinarian'): ?>
                                <a href="../medical_records/create.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn-primary">
                                    <i class="fas fa-plus"></i> Add Medical Record
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (empty($medicalRecords)): ?>
                            <div class="empty-state">
                                <img src="../../assets/images/empty-records.svg" alt="No Records">
                                <p>No medical records found for this pet.</p>
                                <?php if ($user['user_type'] === 'veterinarian'): ?>
                                    <a href="../medical_records/create.php?pet_id=<?php echo $pet['pet_id']; ?>" class="btn-primary">
                                        <i class="fas fa-plus"></i> Add Medical Record
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="records-timeline">
                                <?php foreach ($medicalRecords as $record): ?>
                                    <div class="record-card">
                                        <div class="record-header">
                                            <div class="record-date">
                                                <span class="date"><?php echo formatDate($record['record_date'], 'M d'); ?></span>
                                                <span class="year"><?php echo formatDate($record['record_date'], 'Y'); ?></span>
                                            </div>
                                            <div class="record-title">
                                                <h4><?php echo htmlspecialchars($record['visit_type']); ?></h4>
                                                <p>
                                                    <i class="fas fa-user-md"></i>
                                                    <?php echo $record['vet_name'] ? 'Dr. ' . htmlspecialchars($record['vet_name']) : 'Not specified'; ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="record-body">
                                            <?php if (!empty($record['diagnosis'])): ?>
                                                <div class="record-section">
                                                    <h5>Diagnosis</h5>
                                                    <p><?php echo htmlspecialchars($record['diagnosis']); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($record['treatment'])): ?>
                                                <div class="record-section">
                                                    <h5>Treatment</h5>
                                                    <p><?php echo nl2br(htmlspecialchars($record['treatment'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($record['notes'])): ?>
                                                <div class="record-section">
                                                    <h5>Notes</h5>
                                                    <p><?php echo nl2br(htmlspecialchars($record['notes'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($record['follow_up_date'])): ?>