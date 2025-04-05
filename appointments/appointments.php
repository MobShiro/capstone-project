<?php
session_start();
require_once('../config.php');

class AppointmentManager {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    // Schedule new appointment
    public function scheduleAppointment($data) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO appointments (pet_id, vet_id, owner_id, appointment_date, reason, status)
                VALUES (:pet_id, :vet_id, :owner_id, :appointment_date, :reason, 'scheduled')
            ");
            
            $stmt->execute([
                ':pet_id' => $data['pet_id'],
                ':vet_id' => $data['vet_id'],
                ':owner_id' => $_SESSION['user_id'],
                ':appointment_date' => $data['appointment_date'],
                ':reason' => $data['reason']
            ]);
            
            return true;
        } catch(PDOException $e) {
            error_log("Error scheduling appointment: " . $e->getMessage());
            return false;
        }
    }
    
    // Get upcoming appointments
    public function getUpcomingAppointments() {
        try {
            $stmt = $this->conn->prepare("
                SELECT a.*, p.name as pet_name, u.username as vet_name 
                FROM appointments a 
                JOIN pets p ON a.pet_id = p.pet_id 
                JOIN users u ON a.vet_id = u.user_id 
                WHERE a.owner_id = :owner_id 
                AND a.appointment_date >= CURRENT_DATE 
                AND a.status = 'scheduled' 
                ORDER BY a.appointment_date ASC
            ");
            
            $stmt->execute([':owner_id' => $_SESSION['user_id']]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching upcoming appointments: " . $e->getMessage());
            return [];
        }
    }
    
    // Update appointment status
    public function updateAppointmentStatus($appointment_id, $status) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE appointments 
                SET status = :status 
                WHERE appointment_id = :appointment_id 
                AND (owner_id = :user_id OR vet_id = :user_id)
            ");
            
            return $stmt->execute([
                ':status' => $status,
                ':appointment_id' => $appointment_id,
                ':user_id' => $_SESSION['user_id']
            ]);
        } catch(PDOException $e) {
            error_log("Error updating appointment status: " . $e->getMessage());
            return false;
        }
    }
    
    // Cancel appointment
    public function cancelAppointment($appointment_id) {
        return $this->updateAppointmentStatus($appointment_id, 'cancelled');
    }
    
    // Get available veterinarians
    public function getAvailableVets($date) {
        try {
            $stmt = $this->conn->prepare("
                SELECT u.user_id, u.username 
                FROM users u 
                WHERE u.user_type = 'veterinarian' 
                AND u.user_id NOT IN (
                    SELECT vet_id 
                    FROM appointments 
                    WHERE DATE(appointment_date) = DATE(:date) 
                    AND status = 'scheduled'
                )
            ");
            
            $stmt->execute([':date' => $date]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching available vets: " . $e->getMessage());
            return [];
        }
    }
    
    // Get appointment details
    public function getAppointmentDetails($appointment_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT a.*, p.name as pet_name, u.username as vet_name 
                FROM appointments a 
                JOIN pets p ON a.pet_id = p.pet_id 
                JOIN users u ON a.vet_id = u.user_id 
                WHERE a.appointment_id = :appointment_id 
                AND (a.owner_id = :user_id OR a.vet_id = :user_id)
            ");
            
            $stmt->execute([
                ':appointment_id' => $appointment_id,
                ':user_id' => $_SESSION['user_id']
            ]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Error fetching appointment details: " . $e->getMessage());
            return false;
        }
    }
}
?>