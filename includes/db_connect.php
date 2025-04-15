<?php
require_once __DIR__ . '/../config/config.php';

function getDbConnection() {
    static $connection;
    
    if (!isset($connection)) {
        try {
            $connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            // Check connection
            if ($connection->connect_error) {
                throw new Exception("Connection failed: " . $connection->connect_error);
            }
            
            $connection->set_charset("utf8mb4");
        } catch (Exception $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    return $connection;
}

// Close the database connection
function closeDbConnection() {
    $connection = getDbConnection();
    if ($connection) {
        $connection->close();
    }
}
?>