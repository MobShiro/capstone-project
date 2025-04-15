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

// Process form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_pet'])) {
    // Get form data
    $name = sanitizeInput($_POST['name']);
    $species = sanitizeInput($_POST['species']);
    $breed = sanitizeInput($_POST['breed']);
    $dateOfBirth = sanitizeInput($_POST['date_of_birth']);
    $gender = sanitizeInput($_POST['gender']);
    $color = sanitizeInput($_POST['color']);
    $weight = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
    $microchipNumber = sanitizeInput($_POST['microchip_number']);
    $notes = sanitizeInput($_POST['notes']);
    
    // Validate form data
    if (empty($name) || empty($species) || empty($gender)) {
        $error = 'Please fill in all required fields.';
    } else {
        $conn = getDbConnection();
        
        // Handle file upload
        $photoPath = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = uploadFile($_FILES['photo'], UPLOAD_PATH . '/pets', ['jpg', 'jpeg', 'png', 'gif']);
            
            if ($uploadResult['success']) {
                $photoPath = $uploadResult['file_path'];
            } else {
                $error = 'Error uploading photo: ' . $uploadResult['message'];
            }
        }
        
        if (empty($error)) {
            // Insert new pet
            $stmt = $conn->prepare("INSERT INTO pets (owner_id, name, species, breed, date_of_birth, gender, color, weight, microchip_number, photo, notes, registered_date) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            
            $stmt->bind_param("isssssdssss", 
                            $user['user_id'], 
                            $name, 
                            $species, 
                            $breed, 
                            $dateOfBirth, 
                            $gender, 
                            $color, 
                            $weight, 
                            $microchipNumber, 
                            $photoPath, 
                            $notes);
            
            if ($stmt->execute()) {
                $petId = $stmt->insert_id;
                $success = 'Pet added successfully!';
                
                // Log activity
                logActivity($user['user_id'], 'Added new pet', 'Pet ID: ' . $petId);
                
                // Redirect to pet details page after successful addition
                header('Location: pet_details.php?id=' . $petId);
                exit;
            } else {
                $error = 'Failed to add pet: ' . $conn->error;
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
    <title>Add New Pet - Vet Anywhere</title>
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
                    <h1>Add New Pet</h1>
                    <nav class="breadcrumb">
                        <a href="../owner_dashboard.php">Dashboard</a> /
                        <a href="my_pets.php">My Pets</a> /
                        <span>Add New Pet</span>
                    </nav>
                </div>

                <!-- Add Pet Form -->
                <div class="card form-card">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="" enctype="multipart/form-data">
                        <div class="form-section">
                            <h3>Basic Information</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name">Pet Name <span class="required">*</span></label>
                                    <input type="text" id="name" name="name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="species">Species <span class="required">*</span></label>
                                    <select id="species" name="species" required>
                                        <option value="">Select Species</option>
                                        <option value="Dog">Dog</option>
                                        <option value="Cat">Cat</option>
                                        <option value="Bird">Bird</option>
                                        <option value="Fish">Fish</option>
                                        <option value="Rabbit">Rabbit</option>
                                        <option value="Hamster">Hamster</option>
                                        <option value="Guinea Pig">Guinea Pig</option>
                                        <option value="Reptile">Reptile</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="breed">Breed</label>
                                    <input type="text" id="breed" name="breed">
                                </div>
                                
                                <div class="form-group">
                                    <label for="gender">Gender <span class="required">*</span></label>
                                    <select id="gender" name="gender" required>
                                        <option value="">Select Gender</option>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Unknown">Unknown</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="color">Color</label>
                                    <input type="text" id="color" name="color">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>Additional Details</h3>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="weight">Weight (kg)</label>
                                    <input type="number" id="weight" name="weight" step="0.01" min="0">
                                </div>
                                
                                <div class="form-group">
                                    <label for="microchip_number">Microchip Number</label>
                                    <input type="text" id="microchip_number" name="microchip_number">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="photo">Pet Photo</label>
                                <div class="file-upload">
                                    <input type="file" id="photo" name="photo" accept="image/*">
                                    <label for="photo" class="file-upload-label">
                                        <i class="fas fa-cloud-upload-alt"></i> Choose a file
                                    </label>
                                    <span class="file-name">No file chosen</span>
                                </div>
                                <small>Maximum file size: 5MB. Accepted formats: JPG, JPEG, PNG, GIF</small>
                                <div class="image-preview" id="imagePreview"></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="notes">Notes</label>
                                <textarea id="notes" name="notes" rows="4"></textarea>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="my_pets.php" class="btn-outline">Cancel</a>
                            <button type="submit" name="add_pet" class="btn-primary">Add Pet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        // File upload preview
        document.getElementById('photo').addEventListener('change', function(e) {
            const fileInput = e.target;
            const fileName = fileInput.files[0]?.name || 'No file chosen';
            const fileNameElement = fileInput.nextElementSibling.nextElementSibling;
            fileNameElement.textContent = fileName;
            
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (fileInput.files && fileInput.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    preview.appendChild(img);
                };
                
                reader.readAsDataURL(fileInput.files[0]);
            }
        });
        
        // Species-dependent breed dropdown
        document.getElementById('species').addEventListener('change', function() {
            const species = this.value;
            const breedInput = document.getElementById('breed');
            
            // Clear current breed value
            breedInput.value = '';
            
            // This could be enhanced to populate breeds based on species from a database
            if (species === 'Dog') {
                // Example: Convert to a dropdown with common dog breeds
                console.log('Dog selected - could show dog breeds');
            } else if (species === 'Cat') {
                // Example: Convert to a dropdown with common cat breeds
                console.log('Cat selected - could show cat breeds');
            }
        });
    </script>
</body>
</html>