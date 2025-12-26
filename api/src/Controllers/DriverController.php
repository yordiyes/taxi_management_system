<?php
namespace Api\Controllers;

use PDO;

class DriverController {
    private $db;
    private $requestMethod;
    private $driverId;

    public function __construct($db, $requestMethod, $driverId) {
        $this->db = $db;
        $this->requestMethod = $requestMethod;
        $this->driverId = $driverId;
    }

    public function processRequest() {
        switch ($this->requestMethod) {
            case 'GET':
                if ($this->driverId) {
                    $this->getDriver($this->driverId);
                } else {
                    $this->getAllDrivers();
                }
                break;
            case 'POST':
                $this->createDriver();
                break;
            case 'PUT':
                $this->updateDriver($this->driverId);
                break;
            case 'DELETE':
                $this->deleteDriver($this->driverId);
                break;
            default:
                $this->notFoundResponse();
                break;
        }
    }

    private function getAllDrivers() {
        $this->checkSession();
        
        // If driver, only return their own profile
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') {
            $query = "SELECT d.*, v.make, v.model FROM drivers d LEFT JOIN vehicles v ON d.vehicle_id = v.id WHERE d.user_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(1, $_SESSION['user_id']);
            $stmt->execute();
            $drivers = array();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) array_push($drivers, $row);
            echo json_encode($drivers);
            return;
        }
        
        // Admin/Manager view all drivers
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }
        
        $query = "SELECT d.*, v.make, v.model FROM drivers d LEFT JOIN vehicles v ON d.vehicle_id = v.id ORDER BY d.created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $drivers = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) array_push($drivers, $row);
        echo json_encode($drivers);
    }

    private function getDriver($id) {
        $this->checkSession();
        
        // If driver, verify they can only access their own profile
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') {
            $query = "SELECT * FROM drivers WHERE id = ? AND user_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(1, $id);
            $stmt->bindParam(2, $_SESSION['user_id']);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if($row) {
                echo json_encode($row);
            } else {
                http_response_code(403);
                echo json_encode(array("message" => "Access Denied. You can only view your own profile."));
            }
            return;
        }
        
        // Admin/Manager can view any driver
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }
        
        $query = "SELECT * FROM drivers WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) echo json_encode($row);
        else { http_response_code(404); echo json_encode(array("message" => "Driver not found.")); }
    }

    private function createDriver() {
        $this->checkAuth();
        $data = json_decode(file_get_contents("php://input"));

        if(!empty($data->name) && !empty($data->license_number)) {
            // If user_id is provided, use it; otherwise require admin/manager
            $user_id = null;
            if (!empty($data->user_id)) {
                $user_id = $data->user_id;
            } elseif (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager')) {
                // Admin/Manager can create drivers without user_id (legacy support)
                $user_id = null;
            } else {
                http_response_code(400);
                echo json_encode(array("message" => "user_id is required."));
                exit();
            }

            $query = "INSERT INTO drivers SET user_id=:user_id, name=:name, license_number=:license_number, phone=:phone, vehicle_id=:vehicle_id, status=:status";
            $stmt = $this->db->prepare($query);

            $data->name = htmlspecialchars(strip_tags($data->name));
            $data->license_number = htmlspecialchars(strip_tags($data->license_number));
            $data->phone = htmlspecialchars(strip_tags($data->phone));
            $data->vehicle_id = !empty($data->vehicle_id) ? $data->vehicle_id : null;
            $data->status = isset($data->status) ? $data->status : 'available';

            $stmt->bindParam(":user_id", $user_id);
            $stmt->bindParam(":name", $data->name);
            $stmt->bindParam(":license_number", $data->license_number);
            $stmt->bindParam(":phone", $data->phone);
            $stmt->bindParam(":vehicle_id", $data->vehicle_id);
            $stmt->bindParam(":status", $data->status);

            if($stmt->execute()) {
                http_response_code(201);
                echo json_encode(array("message" => "Driver created."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to create driver."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Incomplete data."));
        }
    }

    private function updateDriver($id) {
        $this->checkSession();
        $data = json_decode(file_get_contents("php://input"));

        // Verify ownership if driver
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') {
            $check_query = "SELECT id FROM drivers WHERE id = ? AND user_id = ?";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(1, $id);
            $check_stmt->bindParam(2, $_SESSION['user_id']);
            $check_stmt->execute();
            $driver_row = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$driver_row) {
                http_response_code(403);
                echo json_encode(array("message" => "Access Denied. You can only update your own profile."));
                exit();
            }
        } elseif (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }

        if($id && !empty($data->name)) {
            // Drivers can't change status or vehicle_id (admin/manager only)
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') {
                $query = "UPDATE drivers SET name=:name, license_number=:license_number, phone=:phone WHERE id=:id";
                $stmt = $this->db->prepare($query);
                
                $data->name = htmlspecialchars(strip_tags($data->name));
                $data->license_number = htmlspecialchars(strip_tags($data->license_number));
                $data->phone = htmlspecialchars(strip_tags($data->phone));
                
                $stmt->bindParam(":name", $data->name);
                $stmt->bindParam(":license_number", $data->license_number);
                $stmt->bindParam(":phone", $data->phone);
                $stmt->bindParam(":id", $id);
            } else {
                // Admin/Manager can update all fields
                $query = "UPDATE drivers SET name=:name, license_number=:license_number, phone=:phone, vehicle_id=:vehicle_id, status=:status WHERE id=:id";
                $stmt = $this->db->prepare($query);

                $data->name = htmlspecialchars(strip_tags($data->name));
                $data->license_number = htmlspecialchars(strip_tags($data->license_number));
                $data->phone = htmlspecialchars(strip_tags($data->phone));
                $data->vehicle_id = !empty($data->vehicle_id) ? $data->vehicle_id : null;
                $data->status = htmlspecialchars(strip_tags($data->status));

                $stmt->bindParam(":name", $data->name);
                $stmt->bindParam(":license_number", $data->license_number);
                $stmt->bindParam(":phone", $data->phone);
                $stmt->bindParam(":vehicle_id", $data->vehicle_id);
                $stmt->bindParam(":status", $data->status);
                $stmt->bindParam(":id", $id);
            }

            if($stmt->execute()) echo json_encode(array("message" => "Driver updated."));
            else { http_response_code(503); echo json_encode(array("message" => "Unable to update driver.")); }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Incomplete data."));
        }
    }

    private function deleteDriver($id) {
        $this->checkAuth();
        if($id) {
            $query = "DELETE FROM drivers WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(1, $id);
            if($stmt->execute()) echo json_encode(array("message" => "Driver deleted."));
            else { http_response_code(503); echo json_encode(array("message" => "Unable to delete driver.")); }
        }
    }

    private function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }
    }

    private function checkSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(array("message" => "Unauthorized. Please login."));
            exit();
        }
    }

    private function notFoundResponse() {
        http_response_code(404);
        echo json_encode(["message" => "Not Found"]);
    }
}
?>
