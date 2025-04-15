<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is a veterinarian
if (!isLoggedIn() || !hasRole('veterinarian')) {
    header('Location: ../../index.php');
    exit;
}

// Get current user information
$user = getCurrentUser();
$conn = getDbConnection();

// Get vet_id from user_id
$vetQuery = $conn->prepare("SELECT vet_id FROM vet_profiles WHERE user_id = ?");
$vetQuery->bind_param("i", $user['user_id']);
$vetQuery->execute();
$vetResult = $vetQuery->get_result();

if ($vetResult->num_rows === 0) {
    // Redirect to complete profile if vet profile not set up
    header('Location: ../profile/complete_vet_profile.php');
    exit;
}

$vetData = $vetResult->fetch_assoc();
$vetId = $vetData['vet_id'];

// Check if pet_id is provided or appointment_id is provided
$petId = isset($_GET['pet_id']) ? (int)$_GET['pet_id'] : 0;
$appointmentId = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
$pet = null;
$appointment = null;

if ($appointmentId > 0) {
    // Get appointment details
    $apptQuery = $conn->prepare("SELECT a.*, p.pet_id, p.name as pet_name, p.species, p.breed, p.gender, p.date_of_birth, 
                               p.owner_id, CONCAT(u.first_name, ' ', u.last_name) as owner_name
                               FROM appointments a
                               JOIN pets p ON a.pet_id = p.pet_id
                               JOIN users u ON p.owner_id = u.user_id
                               WHERE a.appointment_id = ? AND a.vet_id = ?");
    $apptQuery->bind_param("ii", $appointmentId, $vetId);
    $apptQuery->execute();
    $apptResult = $apptQuery->get_result();
    
    if ($apptResult->num_rows === 0) {
        // Appointment not found or doesn't belong to this vet
        header('Location: ../vet_dashboard.php');
        exit;
    }
    
    $appointment = $apptResult->fetch_assoc();
    $petId = $appointment['pet_id'];
    $pet = [
        'pet_id' => $appointment['pet_id'],
        'name' => $appointment['pet_name'],
        'species' => $appointment['species'],
        'breed' => $appointment['breed'],
        'gender' => $appointment['gender'],
        'date_of_birth' => $appointment['date_of_birth'],
        'owner_id' => $appointment['owner_id'],
        'owner_name' => $appointment['owner_name']
    ];
} elseif ($petId > 0) {
    // Get pet details
    $petQuery = $conn->prepare("SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) as owner_name
                              FROM pets p
                              JOIN users u ON p.owner_id = u.user_id
                              WHERE p.pet_id = ?");
    $petQuery->bind_param("i", $petId);
    $petQuery->execute();
    $petResult = $petQuery->get_result();
    
    if ($petResult->num_rows === 0) {
        // Pet not found
        header('Location: ../vet_dashboard.php');
        exit;
    }
    
    $pet = $petResult->fetch_assoc();
} else {
    // No pet or appointment provided, go to pet selection
    header('Location: select_pet.php');
    exit;
}

// Process form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_record'])) {
    // Get form data
    $visitType = sanitizeInput($_POST['visit_type']);
    $recordDate = sanitizeInput($_POST['record_date']);
    $recordTime = sanitizeInput($_POST['record_time']);
    $diagnosis = sanitizeInput($_POST['diagnosis']);
    $treatment = sanitizeInput($_POST['treatment']);
    $notes = sanitizeInput($_POST['notes']);
    $followUpDate = !empty($_POST['follow_up_date']) ? sanitizeInput($_POST['follow_up_date']) : null;
    
    // Combine date and time
    $recordDateTime = $recordDate . ' ' . $recordTime . ':00';
    
    // Validate form data
    if (empty($visitType) || empty($recordDate) || empty($recordTime)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Insert medical record
        $stmt = $conn->prepare("INSERT INTO medical_records (pet_id, vet_id, record_date, visit_type, diagnosis, treatment, notes, follow_up_date, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iissssss", $petId, $vetId, $recordDateTime, $visitType, $diagnosis, $treatment, $notes, $followUpDate);
        
        if ($stmt->execute()) {
            $recordId = $stmt->insert_id;
            $success = 'Medical record created successfully!';
            
            // Log activity
            logActivity($user['user_id'], 'Created medical record', 'Pet ID: ' . $petId . ', Record ID: ' . $recordId);
            
            // If this was an appointment, update it to completed
            if ($appointmentId > 0) {
                $updateStmt = $conn->prepare("UPDATE appointments SET status = 'Completed', updated_at = NOW() WHERE appointment_id = ?");
                $updateStmt->bind_param("i", $appointmentId);
                $updateStmt->execute();
            }
            
            // Create a notification/reminder for the pet owner if follow-up is set
            if ($followUpDate) {
                $reminderTitle = 'Follow-Up: ' . $visitType;
                $reminderDescription = "Follow-up for " . $pet['name'] . "'s " . $visitType . " appointment. " . 
                                      "Diagnosis: " . $diagnosis;
                
                $reminderStmt = $conn->prepare("INSERT INTO reminders (pet_id, user_id, reminder_type, reminder_date, title, description, status, created_at) 
                                             VALUES (?, ?, 'Checkup', ?, ?, ?, 'Pending', NOW())");
                $reminderStmt->bind_param("iisss", $petId, $pet['owner_id'], $followUpDate, $reminderTitle, $reminderDescription);
                $reminderStmt->execute();
                
                // Also send a message to the owner
                $messageSubject = "Follow-Up Appointment Scheduled";
                $messageText = "A follow-up appointment has been scheduled for " . $pet['name'] . " on " . formatDate($followUpDate) . 
                              ".\n\nReason: Follow-up for " . $visitType . "\nDiagnosis: " . $diagnosis . 
                              "\n\nPlease contact the clinic to confirm this appointment time.";
                
                $msgStmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message_text, sent_date) 
                                        VALUES (?, ?, ?, ?, NOW())");
                $msgStmt->bind_param("iiss", $user['user_id'], $pet['owner_id'], $messageSubject, $messageText);
                $msgStmt->execute();
            }
            
            // Redirect to the record view page
            header('Location: view.php?id=' . $recordId . '&success=1');
            exit;
        } else {
            $error = 'Failed to create medical record: ' . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Medical Record - Vet Anywhere</title>
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
                    <h1>Create Medical Record</h1>
                    <nav class="breadcrumb">
                        <a href="../vet_dashboard.php">Dashboard</a> /
                        <a href="index.php">Medical Records</a> /
                        <span>Create Record</span>
                    </nav>
                </div>

                <!-- Pet Information Card -->
                <div class="pet-info-card">
                    <div class="pet-info-header">
                        <h3>Patient Information</h3>
                    </div>
                    <div class="pet-info-content">
                        <div class="pet-info-detail">
                            <span class="info-label">Pet Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($pet['name']); ?></span>
                        </div>
                        <div class="pet-info-detail">
                            <span class="info-label">Species:</span>
                            <span class="info-value"><?php echo htmlspecialchars($pet['species']); ?></span>
                        </div>
                        <div class="pet-info-detail">
                            <span class="info-label">Breed:</span>
                            <span class="info-value"><?php echo !empty($pet['breed']) ? htmlspecialchars($pet['breed']) : 'Not specified'; ?></span>
                        </div>
                        <div class="pet-info-detail">
                            <span class="info-label">Gender:</span>
                            <span class="info-value"><?php echo htmlspecialchars($pet['gender']); ?></span>
                        </div>
                        <div class="pet-info-detail">
                            <span class="info-label">Age:</span>
                            <span class="info-value">
                                <?php echo !empty($pet['date_of_birth']) ? calculateAge($pet['date_of_birth']) : 'Unknown'; ?>
                            </span>
                        </div>
                        <div class="pet-info-detail">
                            <span class="info-label">Owner:</span>
                            <span class="info-value"><?php echo htmlspecialchars($pet['owner_name']); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Create Medical Record Form -->
                <div class="card form-card">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="form-section">
                            <h3>Visit Details</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="visit_type">Visit Type <span class="required">*</span></label>
                                    <select id="visit_type" name="visit_type" required>
                                        <option value="">Select Visit Type</option>
                                        <option value="Regular Checkup" <?php echo ($appointment && $appointment['purpose'] === 'Regular Checkup') ? 'selected' : ''; ?>>Regular Checkup</option>
                                        <option value="Vaccination" <?php echo ($appointment && $appointment['purpose'] === 'Vaccination') ? 'selected' : ''; ?>>Vaccination</option>
                                        <option value="Emergency" <?php echo ($appointment && $appointment['purpose'] === 'Emergency') ? 'selected' : ''; ?>>Emergency</option>
                                        <option value="Surgery" <?php echo ($appointment && $appointment['purpose'] === 'Surgery') ? 'selected' : ''; ?>>Surgery</option>
                                        <option value="Dental" <?php echo ($appointment && $appointment['purpose'] === 'Dental') ? 'selected' : ''; ?>>Dental</option>
                                        <option value="Treatment" <?php echo ($appointment && $appointment['purpose'] === 'Treatment') ? 'selected' : ''; ?>>Treatment</option>
                                        <option value="Other" <?php echo ($appointment && $appointment['purpose'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="record_date">Visit Date <span class="required">*</span></label>
                                    <input type="date" id="record_date" name="record_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="record_time">Visit Time <span class="required">*</span></label>
                                    <input type="time" id="record_time" name="record_time" value="<?php echo date('H:i'); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="follow_up_date">Follow-up Date</label>
                                    <input type="date" id="follow_up_date" name="follow_up_date" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>Medical Details</h3>
                            
                            <div class="form-group">
                                <label for="diagnosis">Diagnosis</label>
                                <textarea id="diagnosis" name="diagnosis" rows="3"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="treatment">Treatment</label>
                                <textarea id="treatment" name="treatment" rows="4"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="notes">Additional Notes</label>
                                <textarea id="notes" name="notes" rows="4"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="<?php echo $appointmentId ? '../appointments/upcoming.php' : 'index.php'; ?>" class="btn-outline">Cancel</a>
                            <button type="submit" name="create_record" class="btn-primary">Create Record</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set default date and time if from appointment
            <?php if ($appointment): ?>
            const appointmentDate = "<?php echo date('Y-m-d', strtotime($appointment['appointment_date'])); ?>";
            const appointmentTime = "<?php echo date('H:i', strtotime($appointment['appointment_date'])); ?>";
            document.getElementById('record_date').value = appointmentDate;
            document.getElementById('record_time').value = appointmentTime;
            <?php endif; ?>
            
            // Dynamic follow-up date validation
            const recordDateInput = document.getElementById('record_date');
            const followUpDateInput = document.getElementById('follow_up_date');
            
            recordDateInput.addEventListener('change', function() {
                const recordDate = new Date(this.value);
                const nextDay = new Date(recordDate);
                nextDay.setDate(recordDate.getDate() + 1);
                
                const minDate = nextDay.toISOString().split('T')[0];
                followUpDateInput.setAttribute('min', minDate);
                
                // If current follow-up date is before new minimum, clear it
                if (followUpDateInput.value && new Date(followUpDateInput.value) < nextDay) {
                    followUpDateInput.value = '';
                }
            });
        });
    </script>
</body>
</html>