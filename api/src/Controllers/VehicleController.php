<?php
namespace Api\Controllers;

use PDO;

class VehicleController {
    private $db;
    private $requestMethod;
    private $vehicleId;

    public function __construct($db, $requestMethod, $vehicleId) {
        $this->db = $db;
        $this->requestMethod = $requestMethod;
        $this->vehicleId = $vehicleId;
    }

    public function processRequest() {
        switch ($this->requestMethod) {
            case 'GET':
                if ($this->vehicleId) {
                    $this->getVehicle($this->vehicleId);
                } else {
                    $this->getAllVehicles();
                }
                break;
            case 'POST':
                $this->createVehicle();
                break;
            case 'PUT':
                $this->updateVehicle($this->vehicleId);
                break;
            case 'DELETE':
                $this->deleteVehicle($this->vehicleId);
                break;
            default:
                $this->notFoundResponse();
                break;
        }
    }

    private function getAllVehicles() {
        $this->checkSession();
        
        // If driver, only show their vehicles
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') {
            $driver_query = "SELECT id FROM drivers WHERE user_id = ?";
            $driver_stmt = $this->db->prepare($driver_query);
            $driver_stmt->bindParam(1, $_SESSION['user_id']);
            $driver_stmt->execute();
            $driver_row = $driver_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($driver_row) {
                $query = "SELECT * FROM vehicles WHERE driver_id = ? ORDER BY created_at DESC";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(1, $driver_row['id']);
                $stmt->execute();
                $vehicles = array();
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) array_push($vehicles, $row);
                echo json_encode($vehicles);
                return;
            }
        }
        
        // Admin/Manager view all vehicles
        $query = "SELECT * FROM vehicles ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $vehicles = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) array_push($vehicles, $row);
        echo json_encode($vehicles);
    }

    private function getVehicle($id) {
        $query = "SELECT * FROM vehicles WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) echo json_encode($row);
        else { http_response_code(404); echo json_encode(array("message" => "Vehicle not found.")); }
    }

    private function createVehicle() {
        $this->checkAuth();
        $data = json_decode(file_get_contents("php://input"));

        if(!empty($data->make) && !empty($data->model) && !empty($data->license_plate)) {
            // Get driver_id if user is a driver
            $driver_id = null;
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') {
                $driver_query = "SELECT id FROM drivers WHERE user_id = ?";
                $driver_stmt = $this->db->prepare($driver_query);
                $driver_stmt->bindParam(1, $_SESSION['user_id']);
                $driver_stmt->execute();
                $driver_row = $driver_stmt->fetch(PDO::FETCH_ASSOC);
                if ($driver_row) {
                    $driver_id = $driver_row['id'];
                } else {
                    http_response_code(403);
                    echo json_encode(array("message" => "Driver profile not found. Please contact administrator."));
                    exit();
                }
            }

            $query = "INSERT INTO vehicles SET driver_id=:driver_id, make=:make, model=:model, license_plate=:license_plate, type=:type, capacity=:capacity, status=:status";
            $stmt = $this->db->prepare($query);

            $data->make = htmlspecialchars(strip_tags($data->make));
            $data->model = htmlspecialchars(strip_tags($data->model));
            $data->license_plate = htmlspecialchars(strip_tags($data->license_plate));
            $data->type = htmlspecialchars(strip_tags($data->type));
            $data->capacity = htmlspecialchars(strip_tags($data->capacity));
            $data->status = isset($data->status) ? $data->status : 'available';

            $stmt->bindParam(":driver_id", $driver_id);
            $stmt->bindParam(":make", $data->make);
            $stmt->bindParam(":model", $data->model);
            $stmt->bindParam(":license_plate", $data->license_plate);
            $stmt->bindParam(":type", $data->type);
            $stmt->bindParam(":capacity", $data->capacity);
            $stmt->bindParam(":status", $data->status);

            if($stmt->execute()) {
                http_response_code(201);
                echo json_encode(array("message" => "Vehicle created."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to create vehicle."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Incomplete data."));
        }
    }

    private function updateVehicle($id) {
        $this->checkAuth();
        $data = json_decode(file_get_contents("php://input"));

        // Check ownership if driver
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') {
            $driver_query = "SELECT id FROM drivers WHERE user_id = ?";
            $driver_stmt = $this->db->prepare($driver_query);
            $driver_stmt->bindParam(1, $_SESSION['user_id']);
            $driver_stmt->execute();
            $driver_row = $driver_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($driver_row) {
                $check_query = "SELECT driver_id FROM vehicles WHERE id = ?";
                $check_stmt = $this->db->prepare($check_query);
                $check_stmt->bindParam(1, $id);
                $check_stmt->execute();
                $vehicle_row = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$vehicle_row || $vehicle_row['driver_id'] != $driver_row['id']) {
                    http_response_code(403);
                    echo json_encode(array("message" => "Access Denied. You can only update your own vehicles."));
                    exit();
                }
            }
        }

        if($id && !empty($data->make)) {
            $query = "UPDATE vehicles SET make=:make, model=:model, license_plate=:license_plate, type=:type, capacity=:capacity, status=:status WHERE id=:id";
            $stmt = $this->db->prepare($query);

            $data->make = htmlspecialchars(strip_tags($data->make));
            $data->model = htmlspecialchars(strip_tags($data->model));
            $data->license_plate = htmlspecialchars(strip_tags($data->license_plate));
            $data->type = htmlspecialchars(strip_tags($data->type));
            $data->capacity = htmlspecialchars(strip_tags($data->capacity));
            $data->status = htmlspecialchars(strip_tags($data->status));

            $stmt->bindParam(":make", $data->make);
            $stmt->bindParam(":model", $data->model);
            $stmt->bindParam(":license_plate", $data->license_plate);
            $stmt->bindParam(":type", $data->type);
            $stmt->bindParam(":capacity", $data->capacity);
            $stmt->bindParam(":status", $data->status);
            $stmt->bindParam(":id", $id);

            if($stmt->execute()) {
                echo json_encode(array("message" => "Vehicle updated."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to update vehicle."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Incomplete data or missing ID."));
        }
    }

    private function deleteVehicle($id) {
        $this->checkAuth();
        
        // Check ownership if driver
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') {
            $driver_query = "SELECT id FROM drivers WHERE user_id = ?";
            $driver_stmt = $this->db->prepare($driver_query);
            $driver_stmt->bindParam(1, $_SESSION['user_id']);
            $driver_stmt->execute();
            $driver_row = $driver_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($driver_row) {
                $check_query = "SELECT driver_id FROM vehicles WHERE id = ?";
                $check_stmt = $this->db->prepare($check_query);
                $check_stmt->bindParam(1, $id);
                $check_stmt->execute();
                $vehicle_row = $check_stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$vehicle_row || $vehicle_row['driver_id'] != $driver_row['id']) {
                    http_response_code(403);
                    echo json_encode(array("message" => "Access Denied. You can only delete your own vehicles."));
                    exit();
                }
            }
        }
        
        if($id) {
            $query = "DELETE FROM vehicles WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(1, $id);

            if($stmt->execute()) {
                echo json_encode(array("message" => "Vehicle deleted."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to delete vehicle."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Missing ID."));
        }
    }

    private function checkAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager' && $_SESSION['role'] !== 'driver')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }
    }

    private function checkSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function notFoundResponse() {
        http_response_code(404);
        echo json_encode(["message" => "Not Found"]);
    }
}
?>
