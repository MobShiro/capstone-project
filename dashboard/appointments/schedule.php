<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is a pet owner
if (!isLoggedIn() || !hasRole('pet_owner')) {
    header('Location: ../../index.php');
    exit;
}

// Get current user information
$user = getCurrentUser();

// Get user's pets
$conn = getDbConnection();
$stmt = $conn->prepare("SELECT pet_id, name FROM pets WHERE owner_id = ? AND status = 'active' ORDER BY name");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$petsResult = $stmt->get_result();
$pets = [];
while ($pet = $petsResult->fetch_assoc()) {
    $pets[] = $pet;
}

// Get available veterinarians
$stmt = $conn->prepare("SELECT vp.vet_id, u.first_name, u.last_name, vp.specialization, vp.clinic_name 
                      FROM vet_profiles vp 
                      JOIN users u ON vp.user_id = u.user_id 
                      WHERE u.status = 'active' 
                      ORDER BY u.last_name, u.first_name");
$stmt->execute();
$vetsResult = $stmt->get_result();
$vets = [];
while ($vet = $vetsResult->fetch_assoc()) {
    $vets[] = $vet;
}

// Process form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_appointment'])) {
    // Get form data
    $petId = (int)$_POST['pet_id'];
    $vetId = (int)$_POST['vet_id'];
    $appointmentDate = sanitizeInput($_POST['appointment_date']);
    $appointmentTime = sanitizeInput($_POST['appointment_time']);
    $purpose = sanitizeInput($_POST['purpose']);
    $notes = sanitizeInput($_POST['notes']);
    
    // Combine date and time
    $appointmentDateTime = $appointmentDate . ' ' . $appointmentTime . ':00';
    
    // Validate form data
    if (empty($petId) || empty($vetId) || empty($appointmentDate) || empty($appointmentTime) || empty($purpose)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if pet belongs to the user
        $stmt = $conn->prepare("SELECT pet_id FROM pets WHERE pet_id = ? AND owner_id = ?");
        $stmt->bind_param("ii", $petId, $user['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error = 'Invalid pet selection.';
        } else {
            // Check if the appointment time is available
            $stmt = $conn->prepare("SELECT appointment_id FROM appointments 
                                  WHERE vet_id = ? 
                                  AND appointment_date = ? 
                                  AND status = 'Scheduled'");
            $stmt->bind_param("is", $vetId, $appointmentDateTime);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'This appointment time is already booked. Please select a different time.';
            } else {
                // Insert new appointment
                $stmt = $conn->prepare("INSERT INTO appointments (pet_id, vet_id, appointment_date, purpose, status, notes, created_at) 
                                      VALUES (?, ?, ?, ?, 'Scheduled', ?, NOW())");
                $stmt->bind_param("iisss", $petId, $vetId, $appointmentDateTime, $purpose, $notes);
                
                if ($stmt->execute()) {
                    $appointmentId = $stmt->insert_id;
                    $success = 'Appointment scheduled successfully!';
                    
                    // Create a reminder for this appointment
                    $reminderTitle = 'Appointment: ' . $purpose;
                    $reminderDate = date('Y-m-d', strtotime($appointmentDateTime));
                    
                    $stmt = $conn->prepare("INSERT INTO reminders (pet_id, user_id, reminder_type, reminder_date, title, description, status, created_at) 
                                          VALUES (?, ?, 'Appointment', ?, ?, ?, 'Pending', NOW())");
                    $reminderDescription = "Appointment with vet at " . date('g:i A', strtotime($appointmentDateTime));
                    $stmt->bind_param("iisss", $petId, $user['user_id'], $reminderDate, $reminderTitle, $reminderDescription);
                    $stmt->execute();
                    
                    // Log activity
                    logActivity($user['user_id'], 'Scheduled appointment', 'Appointment ID: ' . $appointmentId);
                    
                    // Notify veterinarian (in a real system, this would send an email)
                    $petName = '';
                    foreach ($pets as $pet) {
                        if ($pet['pet_id'] == $petId) {
                            $petName = $pet['name'];
                            break;
                        }
                    }
                    
                    $vetUserId = 0;
                    foreach ($vets as $vet) {
                        if ($vet['vet_id'] == $vetId) {
                            $vetName = $vet['first_name'] . ' ' . $vet['last_name'];
                            // Get vet's user_id
                            $getVetStmt = $conn->prepare("SELECT user_id FROM vet_profiles WHERE vet_id = ?");
                            $getVetStmt->bind_param("i", $vetId);
                            $getVetStmt->execute();
                            $vetUserResult = $getVetStmt->get_result();
                            if ($vetUserData = $vetUserResult->fetch_assoc()) {
                                $vetUserId = $vetUserData['user_id'];
                            }
                            break;
                        }
                    }
                    
                    // Create a notification message
                    if ($vetUserId > 0) {
                        $messageSubject = "New Appointment Scheduled";
                        $messageText = "A new appointment has been scheduled for {$petName} on " . date('F j, Y \a\t g:i A', strtotime($appointmentDateTime)) . 
                                      ".\n\nPurpose: {$purpose}\n\nNotes: {$notes}";
                        
                        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message_text, sent_date) 
                                              VALUES (?, ?, ?, ?, NOW())");
                        $stmt->bind_param("iiss", $user['user_id'], $vetUserId, $messageSubject, $messageText);
                        $stmt->execute();
                    }
                    
                    // Redirect to upcoming appointments after successful scheduling
                    header('Location: upcoming.php?success=1');
                    exit;
                } else {
                    $error = 'Failed to schedule appointment: ' . $conn->error;
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment - Vet Anywhere</title>
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
                    <h1>Schedule Appointment</h1>
                    <nav class="breadcrumb">
                        <a href="../owner_dashboard.php">Dashboard</a> /
                        <a href="upcoming.php">Appointments</a> /
                        <span>Schedule Appointment</span>
                    </nav>
                </div>

                <!-- Schedule Appointment Form -->
                <div class="card form-card">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <?php if (empty($pets)): ?>
                        <div class="alert alert-info">
                            <p>You need to add a pet before scheduling an appointment.</p>
                            <a href="../pets/add_pet.php" class="btn-primary">Add Pet</a>
                        </div>
                    <?php elseif (empty($vets)): ?>
                        <div class="alert alert-info">
                            <p>There are no veterinarians available in the system. Please try again later.</p>
                        </div>
                    <?php else: ?>
                        <form method="post" action="">
                            <div class="form-section">
                                <h3>Appointment Details</h3>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="pet_id">Select Pet <span class="required">*</span></label>
                                        <select id="pet_id" name="pet_id" required>
                                            <option value="">Select Pet</option>
                                            <?php foreach ($pets as $pet): ?>
                                                <option value="<?php echo $pet['pet_id']; ?>"><?php echo htmlspecialchars($pet['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="vet_id">Select Veterinarian <span class="required">*</span></label>
                                        <select id="vet_id" name="vet_id" required>
                                            <option value="">Select Veterinarian</option>
                                            <?php foreach ($vets as $vet): ?>
                                                <option value="<?php echo $vet['vet_id']; ?>">
                                                    Dr. <?php echo htmlspecialchars($vet['first_name'] . ' ' . $vet['last_name']); ?>
                                                    <?php if (!empty($vet['specialization'])): ?>
                                                        (<?php echo htmlspecialchars($vet['specialization']); ?>)
                                                    <?php endif; ?>
                                                    <?php if (!empty($vet['clinic_name'])): ?>
                                                        - <?php echo htmlspecialchars($vet['clinic_name']); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="appointment_date">Date <span class="required">*</span></label>
                                        <input type="date" id="appointment_date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="appointment_time">Time <span class="required">*</span></label>
                                        <input type="time" id="appointment_time" name="appointment_time" required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="purpose">Purpose <span class="required">*</span></label>
                                    <select id="purpose" name="purpose" required>
                                        <option value="">Select Purpose</option>
                                        <option value="Regular Checkup">Regular Checkup</option>
                                        <option value="Vaccination">Vaccination</option>
                                        <option value="Illness/Injury">Illness/Injury</option>
                                        <option value="Surgery">Surgery</option>
                                        <option value="Dental">Dental</option>
                                        <option value="Grooming">Grooming</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="notes">Additional Notes</label>
                                    <textarea id="notes" name="notes" rows="4"></textarea>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <a href="upcoming.php" class="btn-outline">Cancel</a>
                                <button type="submit" name="schedule_appointment" class="btn-primary">Schedule Appointment</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        // Initialize date constraints
        document.addEventListener('DOMContentLoaded', function() {
            // Set min date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('appointment_date').setAttribute('min', today);
            
            // Time slots could be dynamically loaded based on vet availability
            document.getElementById('vet_id').addEventListener('change', function() {
                const vetId = this.value;
                const dateInput = document.getElementById('appointment_date');
                
                if (vetId && dateInput.value) {
                    // In a real application, you would fetch available time slots from the server
                    console.log('Could load available time slots for vet ID: ' + vetId);
                }
            });
            
            document.getElementById('appointment_date').addEventListener('change', function() {
                const date = this.value;
                const vetId = document.getElementById('vet_id').value;
                
                if (date && vetId) {
                    // In a real application, you would fetch available time slots from the server
                    console.log('Could load available time slots for date: ' + date);
                }
            });
        });
    </script>
</body>
</html>