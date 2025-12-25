<?php
namespace Api\Controllers;

use PDO;

class ServiceController {
    private $db;
    private $requestMethod;
    private $serviceId;

    public function __construct($db, $requestMethod, $serviceId) {
        $this->db = $db;
        $this->requestMethod = $requestMethod;
        $this->serviceId = $serviceId;
    }

    public function processRequest() {
        switch ($this->requestMethod) {
            case 'GET':
                if ($this->serviceId) {
                    $this->getService($this->serviceId);
                } else {
                    $this->getAllServices();
                }
                break;
            case 'POST':
                $this->createService();
                break;
            case 'PUT':
                $this->updateService($this->serviceId);
                break;
            case 'DELETE':
                $this->deleteService($this->serviceId);
                break;
            default:
                $this->notFoundResponse();
                break;
        }
    }

    private function getAllServices() {
        $query = "SELECT * FROM services ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $services = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            array_push($services, $row);
        }
        echo json_encode($services);
    }

    private function getService($id) {
        $query = "SELECT * FROM services WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(1, $id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            echo json_encode($row);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "Service not found."));
        }
    }

    private function createService() {
        $this->checkAuth();
        $data = json_decode(file_get_contents("php://input"));

        if(!empty($data->name) && !empty($data->base_price) && !empty($data->price_per_km)) {
            $query = "INSERT INTO services SET name=:name, description=:description, base_price=:base_price, price_per_km=:price_per_km";
            $stmt = $this->db->prepare($query);

            $data->name = htmlspecialchars(strip_tags($data->name));
            $data->description = htmlspecialchars(strip_tags($data->description));
            $data->base_price = htmlspecialchars(strip_tags($data->base_price));
            $data->price_per_km = htmlspecialchars(strip_tags($data->price_per_km));

            $stmt->bindParam(":name", $data->name);
            $stmt->bindParam(":description", $data->description);
            $stmt->bindParam(":base_price", $data->base_price);
            $stmt->bindParam(":price_per_km", $data->price_per_km);

            if($stmt->execute()) {
                http_response_code(201);
                echo json_encode(array("message" => "Service created."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to create service."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Incomplete data."));
        }
    }

    private function updateService($id) {
        $this->checkAuth();
        $data = json_decode(file_get_contents("php://input"));

        if($id && !empty($data->name)) {
            $query = "UPDATE services SET name=:name, description=:description, base_price=:base_price, price_per_km=:price_per_km WHERE id=:id";
            $stmt = $this->db->prepare($query);

            $data->name = htmlspecialchars(strip_tags($data->name));
            $data->description = htmlspecialchars(strip_tags($data->description));
            $data->base_price = htmlspecialchars(strip_tags($data->base_price));
            $data->price_per_km = htmlspecialchars(strip_tags($data->price_per_km));

            $stmt->bindParam(":name", $data->name);
            $stmt->bindParam(":description", $data->description);
            $stmt->bindParam(":base_price", $data->base_price);
            $stmt->bindParam(":price_per_km", $data->price_per_km);
            $stmt->bindParam(":id", $id);

            if($stmt->execute()) {
                echo json_encode(array("message" => "Service updated."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to update service."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Incomplete data or missing ID."));
        }
    }

    private function deleteService($id) {
        $this->checkAuth();
        if($id) {
            $query = "DELETE FROM services WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(1, $id);

            if($stmt->execute()) {
                echo json_encode(array("message" => "Service deleted."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to delete service."));
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
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }
    }

    private function notFoundResponse() {
        http_response_code(404);
        echo json_encode(["message" => "Not Found"]);
    }
}
?>
