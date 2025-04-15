<?php
require_once __DIR__ . '/db_connect.php';

// Sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Validate date format (YYYY-MM-DD)
function isValidDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// Generate a random string
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    
    return $randomString;
}

// Handle file upload
function uploadFile($file, $destinationPath, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
    // Create directory if it doesn't exist
    if (!file_exists($destinationPath)) {
        mkdir($destinationPath, 0777, true);
    }
    
    // Check if file was uploaded without errors
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = basename($file['name']);
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Check file type
        if (!in_array($fileExtension, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowedTypes)];
        }
        
        // Check file size (max 5MB)
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File size exceeds the maximum limit of 5MB.'];
        }
        
        // Generate unique file name
        $uniqueFileName = generateRandomString() . '_' . time() . '.' . $fileExtension;
        $targetFile = $destinationPath . '/' . $uniqueFileName;
        
        // Move uploaded file to destination
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            return ['success' => true, 'file_path' => $uniqueFileName];
        } else {
            return ['success' => false, 'message' => 'Failed to upload file.'];
        }
    } else {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds the upload_max_filesize directive in php.ini',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds the MAX_FILE_SIZE directive specified in the HTML form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        
        $errorMessage = isset($errorMessages[$file['error']]) 
            ? $errorMessages[$file['error']] 
            : 'Unknown upload error';
        
        return ['success' => false, 'message' => $errorMessage];
    }
}

// Calculate age from date of birth
function calculateAge($dateOfBirth) {
    $dob = new DateTime($dateOfBirth);
    $now = new DateTime();
    $interval = $now->diff($dob);
    
    if ($interval->y > 0) {
        return $interval->y . ' year(s)';
    } elseif ($interval->m > 0) {
        return $interval->m . ' month(s)';
    } else {
        return $interval->d . ' day(s)';
    }
}

// Format date for display
function formatDate($date, $format = 'M d, Y') {
    if ($date) {
        $d = new DateTime($date);
        return $d->format($format);
    }
    return '';
}

// Get upcomingEvents  reminders for a user
function getUpcomingReminders($userId, $days = 7) {
    $conn = getDbConnection();
    $futureDate = date('Y-m-d', strtotime('+' . $days . ' days'));
    $currentDate = date('Y-m-d');
    
    // Get pets owned by the user
    $petsQuery = "SELECT pet_id FROM pets WHERE owner_id = ?";
    $stmt = $conn->prepare($petsQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $petsResult = $stmt->get_result();
    
    $petIds = [];
    while ($pet = $petsResult->fetch_assoc()) {
        $petIds[] = $pet['pet_id'];
    }
    
    if (empty($petIds)) {
        return [];
    }
    
    // Convert pet IDs to comma-separated string for IN clause
    $petIdsStr = implode(',', $petIds);
    
    // Get upcoming reminders
    $query = "SELECT r.*, p.name as pet_name
              FROM reminders r
              JOIN pets p ON r.pet_id = p.pet_id
              WHERE r.user_id = ? 
              AND r.reminder_date BETWEEN ? AND ?
              AND r.status = 'Pending'
              ORDER BY r.reminder_date ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $userId, $currentDate, $futureDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reminders = [];
    while ($row = $result->fetch_assoc()) {
        $reminders[] = $row;
    }
    
    return $reminders;
}

// Get upcoming appointments for a user
function getUpcomingAppointments($userId, $userType, $days = 30) {
    $conn = getDbConnection();
    $futureDate = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
    $currentDate = date('Y-m-d H:i:s');
    
    if ($userType === 'pet_owner') {
        // Get appointments for pet owner
        $query = "SELECT a.*, p.name as pet_name, 
                  CONCAT(u.first_name, ' ', u.last_name) as vet_name
                  FROM appointments a
                  JOIN pets p ON a.pet_id = p.pet_id
                  JOIN vet_profiles vp ON a.vet_id = vp.vet_id
                  JOIN users u ON vp.user_id = u.user_id
                  WHERE p.owner_id = ? 
                  AND a.appointment_date BETWEEN ? AND ?
                  AND a.status = 'Scheduled'
                  ORDER BY a.appointment_date ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iss", $userId, $currentDate, $futureDate);
    } else if ($userType === 'veterinarian') {
        // Get vet_id from user_id
        $vetIdQuery = "SELECT vet_id FROM vet_profiles WHERE user_id = ?";
        $vetStmt = $conn->prepare($vetIdQuery);
        $vetStmt->bind_param("i", $userId);
        $vetStmt->execute();
        $vetResult = $vetStmt->get_result();
        
        if ($vetResult->num_rows === 0) {
            return [];
        }
        
        $vetData = $vetResult->fetch_assoc();
        $vetId = $vetData['vet_id'];
        
        // Get appointments for veterinarian
        $query = "SELECT a.*, p.name as pet_name, 
                  CONCAT(u.first_name, ' ', u.last_name) as owner_name
                  FROM appointments a
                  JOIN pets p ON a.pet_id = p.pet_id
                  JOIN users u ON p.owner_id = u.user_id
                  WHERE a.vet_id = ? 
                  AND a.appointment_date BETWEEN ? AND ?
                  AND a.status = 'Scheduled'
                  ORDER BY a.appointment_date ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iss", $vetId, $currentDate, $futureDate);
    } else {
        return [];
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    
    return $appointments;
}

// Log system activity
function logActivity($userId, $action, $details = '') {
    // In a real application, you'd have a table for activity logs
    // For now, we'll just log to the PHP error log
    $logMessage = date('Y-m-d H:i:s') . " - User ID: $userId - Action: $action - Details: $details";
    error_log($logMessage);
}

// Send notification email
function sendEmail($to, $subject, $message) {
    // In a real application, you'd use a proper email library like PHPMailer
    // For simplicity, we'll use the built-in mail() function
    $headers = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>' . "\r\n" .
               'Reply-To: ' . MAIL_FROM_ADDRESS . "\r\n" .
               'X-Mailer: PHP/' . phpversion();
    
    return mail($to, $subject, $message, $headers);
}

// Get vaccination due for a pet
function getVaccinationsDue($petId) {
    $conn = getDbConnection();
    $currentDate = date('Y-m-d');
    
    $query = "SELECT v.*, 
              DATEDIFF(v.next_due_date, ?) as days_remaining
              FROM vaccinations v
              WHERE v.pet_id = ? 
              AND v.next_due_date >= ?
              ORDER BY v.next_due_date ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sis", $currentDate, $petId, $currentDate);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $vaccinations = [];
    while ($row = $result->fetch_assoc()) {
        $vaccinations[] = $row;
    }
    
    return $vaccinations;
}

// Check if a value exists in the database
function valueExists($table, $column, $value) {
    $conn = getDbConnection();
    
    $query = "SELECT COUNT(*) as count FROM $table WHERE $column = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $value);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    return $data['count'] > 0;
}
?>