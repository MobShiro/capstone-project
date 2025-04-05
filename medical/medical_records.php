<?php
session_start();
require_once('../config.php');

class MedicalRecordManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Add new medical record
    public function addMedicalRecord($data) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO medical_records (pet_id, vet_id, visit_date, diagnosis, treatment, notes, next_visit_date)
                VALUES (:pet_id, :vet_id, :visit_date, :diagnosis, :treatment, :notes, :next_visit_date)
            ");
            
            $stmt->execute([
                ':pet_id' => $data['pet_id'],
                ':vet_id' => $_SESSION['user_id'],
                ':visit_date' => $data['visit_date'],
                ':diagnosis' => $data['diagnosis'],
                ':treatment' => $data['treatment'],
                ':notes' => $data['notes'],
                ':next_visit_date' => $data['next_visit_date']
            ]);
            
            // Add vaccination records if provided
            if (!empty($data['vaccinations'])) {
                $this->addVaccinations($data['pet_id'], $data['vaccinations']);
            }
            
            return true;
        } catch(PDOException $e) {
            error_log("Error adding medical record: " . $e->getMessage());
            return false;
        }
    }
    
    // Add vaccinations
    private function addVaccinations($pet_id, $vaccinations) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO vaccinations (pet_id, vaccine_name, date_given, next_due_date, administered_by)
                VALUES (:pet_id, :vaccine_name, :date_given, :next_due_date, :administered_by)
            ");
            
            foreach ($vaccinations as $vaccine) {
                $stmt->execute([
                    ':pet_id' => $pet_id,
                    ':vaccine_name' => $vaccine['name'],
                    ':date_given' => $vaccine['date_given'],
                    ':next_due_date' => $vaccine['next_due_date'],
                    ':administered_by' => $_SESSION['user_id']
                ]);
            }
            
            return true;
        } catch(PDOException $e) {
            error_log("Error adding vaccinations: " . $e->getMessage());
            return false;
        }
    }
    
    // Get due vaccinations
    public function getDueVaccinations() {
        try {
            $stmt = $this->conn->prepare("
                SELECT v.*, p.name as pet_name 
                FROM vaccinations v 
                JOIN pets p ON v.pet_id = p.pet_id 
                WHERE p.owner_id = :owner_id 
                AND v.next_due_date <= DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY)
                ORDER BY v.next_due_date ASC
            ");
            
            $stmt->execute([':owner_id' => $_SESSION['user_id']]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching due vaccinations: " . $e->getMessage());
            return [];
        }
    }
}
?>