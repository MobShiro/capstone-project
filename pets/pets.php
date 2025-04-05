<?php
session_start();
require_once('../config.php');

class PetManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Add new pet
    public function addPet($data) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO pets (owner_id, name, species, breed, birth_date, gender, microchip_number) 
                                        VALUES (:owner_id, :name, :species, :breed, :birth_date, :gender, :microchip_number)");
            
            $stmt->execute([
                ':owner_id' => $_SESSION['user_id'],
                ':name' => $data['name'],
                ':species' => $data['species'],
                ':breed' => $data['breed'],
                ':birth_date' => $data['birth_date'],
                ':gender' => $data['gender'],
                ':microchip_number' => $data['microchip_number']
            ]);
            
            return true;
        } catch(PDOException $e) {
            error_log("Error adding pet: " . $e->getMessage());
            return false;
        }
    }
    
    // Get pet details
    public function getPet($pet_id) {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM pets WHERE pet_id = :pet_id AND owner_id = :owner_id");
            $stmt->execute([
                ':pet_id' => $pet_id,
                ':owner_id' => $_SESSION['user_id']
            ]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching pet details: " . $e->getMessage());
            return false;
        }
    }
    
    // Update pet information
    public function updatePet($pet_id, $data) {
        try {
            $stmt = $this->conn->prepare("UPDATE pets 
                                        SET name = :name, 
                                            species = :species, 
                                            breed = :breed, 
                                            birth_date = :birth_date, 
                                            gender = :gender, 
                                            microchip_number = :microchip_number 
                                        WHERE pet_id = :pet_id AND owner_id = :owner_id");
            
            $data['pet_id'] = $pet_id;
            $data['owner_id'] = $_SESSION['user_id'];
            
            return $stmt->execute($data);
        } catch(PDOException $e) {
            error_log("Error updating pet: " . $e->getMessage());
            return false;
        }
    }
    
    // Get all pets for current user
    public function getUserPets() {
        try {
            $stmt = $this->conn->prepare("SELECT * FROM pets WHERE owner_id = :owner_id ORDER BY name");
            $stmt->execute([':owner_id' => $_SESSION['user_id']]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching user's pets: " . $e->getMessage());
            return [];
        }
    }
    
    // Delete pet
    public function deletePet($pet_id) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM pets WHERE pet_id = :pet_id AND owner_id = :owner_id");
            return $stmt->execute([
                ':pet_id' => $pet_id,
                ':owner_id' => $_SESSION['user_id']
            ]);
        } catch(PDOException $e) {
            error_log("Error deleting pet: " . $e->getMessage());
            return false;
        }
    }
    
    // Get pet's medical history
    public function getPetMedicalHistory($pet_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT mr.*, u.username as vet_name 
                FROM medical_records mr 
                LEFT JOIN users u ON mr.vet_id = u.user_id 
                WHERE mr.pet_id = :pet_id 
                ORDER BY mr.visit_date DESC
            ");
            
            $stmt->execute([':pet_id' => $pet_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching pet's medical history: " . $e->getMessage());
            return [];
        }
    }
    
    // Get pet's vaccination history
    public function getPetVaccinations($pet_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT v.*, u.username as administered_by_name 
                FROM vaccinations v 
                LEFT JOIN users u ON v.administered_by = u.user_id 
                WHERE v.pet_id = :pet_id 
                ORDER BY v.date_given DESC
            ");
            
            $stmt->execute([':pet_id' => $pet_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching pet's vaccinations: " . $e->getMessage());
            return [];
        }
    }
}
?>