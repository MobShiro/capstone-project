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
$stmt = $conn->prepare("SELECT * FROM pets WHERE owner_id = ? ORDER BY name");
$stmt->bind_param("i", $user['user_id']);
$stmt->execute();
$petsResult = $stmt->get_result();
$pets = [];
while ($pet = $petsResult->fetch_assoc()) {
    $pets[] = $pet;
}

// Handle pet status update (if needed)
$statusMessage = '';
if (isset($_GET['status']) && $_GET['status'] === 'added') {
    $statusMessage = "Pet has been added successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Pets - Vet Anywhere</title>
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
                    <h1>My Pets</h1>
                    <nav class="breadcrumb">
                        <a href="../owner_dashboard.php">Dashboard</a> /
                        <span>My Pets</span>
                    </nav>
                </div>

                <!-- Status Message (if any) -->
                <?php if ($statusMessage): ?>
                    <div class="alert alert-success">
                        <?php echo $statusMessage; ?>
                    </div>
                <?php endif; ?>

                <!-- Pets List Header -->
                <div class="section-header-with-actions">
                    <h2>Your Pets</h2>
                    <a href="add_pet.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Add New Pet
                    </a>
                </div>

                <!-- Pets Grid -->
                <?php if (empty($pets)): ?>
                    <div class="empty-state">
                        <img src="../../assets/images/empty-pets.svg" alt="No Pets">
                        <p>You haven't added any pets yet</p>
                        <a href="add_pet.php" class="btn-primary">Add Your First Pet</a>
                    </div>
                <?php else: ?>
                    <div class="pets-grid">
                        <?php foreach ($pets as $pet): ?>
                            <div class="pet-card">
                                <div class="pet-image">
                                    <?php if ($pet['photo']): ?>
                                        <img src="../../uploads/pets/<?php echo $pet['photo']; ?>" alt="<?php echo htmlspecialchars($pet['name']); ?>">
                                    <?php else: ?>
                                        <img src="../../assets/images/default-pet.png" alt="Default Pet Image">
                                    <?php endif; ?>
                                    
                                    <?php if ($pet['status'] === 'deceased'): ?>
                                        <div class="pet-badge deceased">Deceased</div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="pet-info">
                                    <h3><?php echo htmlspecialchars($pet['name']); ?></h3>
                                    <p class="pet-species">
                                        <?php echo htmlspecialchars($pet['species']); ?>
                                        <?php if ($pet['breed']): ?> 
                                            <span class="pet-breed">(<?php echo htmlspecialchars($pet['breed']); ?>)</span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <div class="pet-details">
                                        <p>
                                            <i class="fas fa-venus-mars"></i> 
                                            <?php echo htmlspecialchars($pet['gender']); ?>
                                        </p>
                                        
                                        <?php if ($pet['date_of_birth']): ?>
                                            <p>
                                                <i class="fas fa-birthday-cake"></i> 
                                                <?php echo calculateAge($pet['date_of_birth']); ?> old
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($pet['weight']): ?>
                                            <p>
                                                <i class="fas fa-weight"></i> 
                                                <?php echo htmlspecialchars($pet['weight']); ?> kg
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="pet-actions">
                                        <a href="pet_details.php?id=<?php echo $pet['pet_id']; ?>" class="btn-primary">
                                            View Details
                                        </a>
                                        
                                        <div class="dropdown">
                                            <button class="btn-outline dropdown-toggle">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a href="edit_pet.php?id=<?php echo $pet['pet_id']; ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="../appointments/schedule.php?pet_id=<?php echo $pet['pet_id']; ?>">
                                                    <i class="fas fa-calendar-plus"></i> Schedule Appointment
                                                </a>
                                                <a href="../health_metrics/add.php?pet_id=<?php echo $pet['pet_id']; ?>">
                                                    <i class="fas fa-weight"></i> Add Health Metrics
                                                </a>
                                                <?php if ($pet['status'] === 'active'): ?>
                                                    <a href="update_status.php?id=<?php echo $pet['pet_id']; ?>&status=deceased" class="text-danger">
                                                        <i class="fas fa-heart-broken"></i> Mark as Deceased
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="pet-care-tips">
                        <h3><i class="fas fa-paw"></i> Pet Care Tips</h3>
                        <div class="tips-carousel">
                            <div class="tip-card">
                                <div class="tip-icon"><i class="fas fa-apple-alt"></i></div>
                                <h4>Healthy Diet</h4>
                                <p>Provide balanced, species-appropriate nutrition for your pet. Consult with your veterinarian for dietary recommendations based on your pet's age, weight, and health status.</p>
                            </div>
                            
                            <div class="tip-card">
                                <div class="tip-icon"><i class="fas fa-heartbeat"></i></div>
                                <h4>Regular Exercise</h4>
                                <p>Daily exercise helps maintain a healthy weight and provides mental stimulation. Different pets have different exercise needs, so tailor activities to your pet's species, age, and health.</p>
                            </div>
                            
                            <div class="tip-card">
                                <div class="tip-icon"><i class="fas fa-tooth"></i></div>
                                <h4>Dental Care</h4>
                                <p>Regular dental care is important for your pet's overall health. Consider dental treats, brushing, and regular professional cleanings to prevent dental disease.</p>
                            </div>
                        </div>
                        
                        <div class="tips-navigation">
                            <button class="tip-nav prev"><i class="fas fa-chevron-left"></i></button>
                            <div class="tip-dots">
                                <span class="dot active"></span>
                                <span class="dot"></span>
                                <span class="dot"></span>
                            </div>
                            <button class="tip-nav next"><i class="fas fa-chevron-right"></i></button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        // Dropdown toggle
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
            
            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const dropdown = this.nextElementSibling;
                    
                    // Close all other dropdowns
                    document.querySelectorAll('.dropdown-menu').forEach(menu => {
                        if (menu !== dropdown) {
                            menu.classList.remove('show');
                        }
                    });
                    
                    // Toggle current dropdown
                    dropdown.classList.toggle('show');
                });
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function() {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.remove('show');
                });
            });
            
            // Pet care tips carousel
            const tipCards = document.querySelectorAll('.tip-card');
            const dots = document.querySelectorAll('.tip-dots .dot');
            const prevBtn = document.querySelector('.tip-nav.prev');
            const nextBtn = document.querySelector('.tip-nav.next');
            let currentIndex = 0;
            
            function showTip(index) {
                tipCards.forEach((card, i) => {
                    card.style.display = i === index ? 'block' : 'none';
                });
                
                dots.forEach((dot, i) => {
                    dot.classList.toggle('active', i === index);
                });
            }
            
            if (tipCards.length > 0) {
                showTip(currentIndex);
                
                if (prevBtn) {
                    prevBtn.addEventListener('click', function() {
                        currentIndex = (currentIndex - 1 + tipCards.length) % tipCards.length;
                        showTip(currentIndex);
                    });
                }
                
                if (nextBtn) {
                    nextBtn.addEventListener('click', function() {
                        currentIndex = (currentIndex + 1) % tipCards.length;
                        showTip(currentIndex);
                    });
                }
                
                dots.forEach((dot, i) => {
                    dot.addEventListener('click', function() {
                        currentIndex = i;
                        showTip(currentIndex);
                    });
                });
            }
        });
    </script>
</body>
</html>