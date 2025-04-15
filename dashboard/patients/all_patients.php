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
$vetStmt = $conn->prepare("SELECT vet_id FROM vet_profiles WHERE user_id = ?");
$vetStmt->bind_param("i", $user['user_id']);
$vetStmt->execute();
$vetResult = $vetStmt->get_result();

if ($vetResult->num_rows === 0) {
    // Redirect to complete vet profile if not set up
    header('Location: ../profile/complete_vet_profile.php');
    exit;
}

$vetData = $vetResult->fetch_assoc();
$vetId = $vetData['vet_id'];

// Get all patients this vet has seen
$patientsStmt = $conn->prepare("
    SELECT DISTINCT p.*, 
    CONCAT(u.first_name, ' ', u.last_name) as owner_name,
    u.phone as owner_phone,
    u.email as owner_email,
    (SELECT MAX(appointment_date) FROM appointments WHERE pet_id = p.pet_id AND vet_id = ?) as last_visit
    FROM pets p
    JOIN appointments a ON p.pet_id = a.pet_id
    JOIN users u ON p.owner_id = u.user_id
    WHERE a.vet_id = ?
    ORDER BY last_visit DESC
");
$patientsStmt->bind_param("ii", $vetId, $vetId);
$patientsStmt->execute();
$patientsResult = $patientsStmt->get_result();

$patients = [];
while ($patient = $patientsResult->fetch_assoc()) {
    $patients[] = $patient;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Patients - Vet Anywhere</title>
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
                    <h1>All Patients</h1>
                    <nav class="breadcrumb">
                        <a href="../vet_dashboard.php">Dashboard</a> /
                        <span>All Patients</span>
                    </nav>
                </div>

                <!-- Search and Filters -->
                <div class="filters-bar">
                    <div class="search-filter">
                        <input type="text" id="patientSearch" placeholder="Search patients...">
                        <i class="fas fa-search"></i>
                    </div>
                    
                    <div class="filter-actions">
                        <select id="speciesFilter">
                            <option value="">All Species</option>
                            <option value="Dog">Dogs</option>
                            <option value="Cat">Cats</option>
                            <option value="Bird">Birds</option>
                            <option value="Rabbit">Rabbits</option>
                            <option value="Other">Other</option>
                        </select>
                        
                        <select id="sortFilter">
                            <option value="last_visit">Last Visit (Recent First)</option>
                            <option value="name">Name (A-Z)</option>
                            <option value="species">Species</option>
                        </select>
                    </div>
                </div>

                <!-- Patients List -->
                <?php if (empty($patients)): ?>
                    <div class="empty-state">
                        <img src="../../assets/images/empty-patients.svg" alt="No Patients">
                        <p>You don't have any patients yet</p>
                    </div>
                <?php else: ?>
                    <div class="patients-grid">
                        <?php foreach ($patients as $patient): ?>
                            <div class="patient-card" data-search="<?php echo strtolower($patient['name'] . ' ' . $patient['species'] . ' ' . $patient['breed'] . ' ' . $patient['owner_name']); ?>" data-species="<?php echo $patient['species']; ?>">
                                <div class="patient-image">
                                    <?php if ($patient['photo']): ?>
                                        <img src="../../uploads/pets/<?php echo $patient['photo']; ?>" alt="<?php echo htmlspecialchars($patient['name']); ?>">
                                    <?php else: ?>
                                        <img src="../../assets/images/default-pet.png" alt="Default Pet Image">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="patient-details">
                                    <h3><?php echo htmlspecialchars($patient['name']); ?></h3>
                                    <p class="patient-species">
                                        <?php echo htmlspecialchars($patient['species']); ?>
                                        <?php if ($patient['breed']): ?> 
                                            <span class="patient-breed">(<?php echo htmlspecialchars($patient['breed']); ?>)</span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <div class="patient-info">
                                        <p>
                                            <i class="fas fa-venus-mars"></i> 
                                            <?php echo htmlspecialchars($patient['gender']); ?>
                                        </p>
                                        
                                        <?php if ($patient['date_of_birth']): ?>
                                            <p>
                                                <i class="fas fa-birthday-cake"></i> 
                                                <?php echo calculateAge($patient['date_of_birth']); ?> old
                                            </p>
                                        <?php endif; ?>
                                        
                                        <p>
                                            <i class="fas fa-user"></i> 
                                            <?php echo htmlspecialchars($patient['owner_name']); ?>
                                        </p>
                                        
                                        <p>
                                            <i class="fas fa-calendar-check"></i> 
                                            Last visit: <?php echo $patient['last_visit'] ? formatDate($patient['last_visit']) : 'Never'; ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="patient-actions">
                                    <a href="../pets/pet_details.php?id=<?php echo $patient['pet_id']; ?>" class="btn-outline">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    <a href="../medical_records/create.php?pet_id=<?php echo $patient['pet_id']; ?>" class="btn-primary">
                                        <i class="fas fa-plus"></i> New Record
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Search functionality
            const searchInput = document.getElementById('patientSearch');
            const patientCards = document.querySelectorAll('.patient-card');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    filterPatients();
                });
            }
            
            // Species filter
            const speciesFilter = document.getElementById('speciesFilter');
            if (speciesFilter) {
                speciesFilter.addEventListener('change', function() {
                    filterPatients();
                });
            }
            
            // Sort filter
            const sortFilter = document.getElementById('sortFilter');
            if (sortFilter) {
                sortFilter.addEventListener('change', function() {
                    sortPatients();
                });
            }
            
            function filterPatients() {
                const searchTerm = searchInput.value.toLowerCase();
                const selectedSpecies = speciesFilter.value;
                
                patientCards.forEach(card => {
                    const searchData = card.getAttribute('data-search').toLowerCase();
                    const species = card.getAttribute('data-species');
                    
                    const matchesSearch = searchData.includes(searchTerm);
                    const matchesSpecies = selectedSpecies === '' || species === selectedSpecies;
                    
                    if (matchesSearch && matchesSpecies) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // After filtering, sort again
                sortPatients();
            }
            
            function sortPatients() {
                const sortValue = sortFilter.value;
                const patientsGrid = document.querySelector('.patients-grid');
                const visibleCards = Array.from(patientCards).filter(card => card.style.display !== 'none');
                
                visibleCards.sort((a, b) => {
                    if (sortValue === 'name') {
                        const nameA = a.querySelector('h3').textContent.toLowerCase();
                        const nameB = b.querySelector('h3').textContent.toLowerCase();
                        return nameA.localeCompare(nameB);
                    } else if (sortValue === 'species') {
                        const speciesA = a.getAttribute('data-species').toLowerCase();
                        const speciesB = b.getAttribute('data-species').toLowerCase();
                        return speciesA.localeCompare(speciesB);
                    }
                    // Default: last_visit (already sorted from the server)
                    return 0;
                });
                
                // Remove and re-add in sorted order
                visibleCards.forEach(card => {
                    patientsGrid.appendChild(card);
                });
            }
        });
    </script>
</body>
</html>