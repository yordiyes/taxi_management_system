<?php
namespace Api\Controllers;

use PDO;

class StatsController {
    private $db;
    private $action;

    public function __construct($db, $action) {
        $this->db = $db;
        $this->action = $action;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Protected: Only Admin/Manager
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }
    }

    public function processRequest() {
        if ($this->action == 'stats') {
            $this->getStats();
        } elseif ($this->action == 'history') {
            $this->getHistory();
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Invalid action."));
        }
    }

    private function getStats() {
        // Visitor Statistics (Mocked/Calculated)
        $stats = array();

        // Count Users
        $query = "SELECT COUNT(*) as total_users FROM users";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

        // Count Bookings
        $query = "SELECT COUNT(*) as total_bookings FROM bookings";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_bookings'];

        // Count Services
        $query = "SELECT COUNT(*) as total_services FROM services";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $stats['total_services'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_services'];

        // Mock Visitor Count (Random for demo)
        $stats['visitors_today'] = rand(10, 100);

        echo json_encode($stats);
    }

    private function getHistory() {
        // Booking History
        $query = "SELECT b.*, u.username, s.name as service_name 
                  FROM bookings b 
                  LEFT JOIN users u ON b.user_id = u.id 
                  LEFT JOIN services s ON b.service_id = s.id 
                  ORDER BY b.created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        $history = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            array_push($history, $row);
        }
        echo json_encode($history);
    }
}
?>
