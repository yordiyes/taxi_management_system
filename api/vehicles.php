<?php
require_once 'cors.php';

require_once 'db.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"));

$id = isset($_GET['id']) ? $_GET['id'] : null;

switch($method) {
    case 'GET':
        // Public API: Anyone can view vehicles (maybe limited info? No, full info is fine for this scope)
        if($id) {
            $query = "SELECT * FROM vehicles WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $id);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if($row) echo json_encode($row);
            else { http_response_code(404); echo json_encode(array("message" => "Vehicle not found.")); }
        } else {
            $query = "SELECT * FROM vehicles ORDER BY created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $vehicles = array();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) array_push($vehicles, $row);
            echo json_encode($vehicles);
        }
        break;

    case 'POST':
        // Protected: Only Admin/Manager
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }

        if(!empty($data->make) && !empty($data->model) && !empty($data->license_plate)) {
            $query = "INSERT INTO vehicles SET make=:make, model=:model, license_plate=:license_plate, type=:type, capacity=:capacity, status=:status";
            $stmt = $db->prepare($query);

            $data->make = htmlspecialchars(strip_tags($data->make));
            $data->model = htmlspecialchars(strip_tags($data->model));
            $data->license_plate = htmlspecialchars(strip_tags($data->license_plate));
            $data->type = htmlspecialchars(strip_tags($data->type));
            $data->capacity = htmlspecialchars(strip_tags($data->capacity));
            $data->status = isset($data->status) ? $data->status : 'available';

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
        break;

    case 'PUT':
        // Protected: Only Admin/Manager
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }

        if($id && !empty($data->make)) {
            $query = "UPDATE vehicles SET make=:make, model=:model, license_plate=:license_plate, type=:type, capacity=:capacity, status=:status WHERE id=:id";
            $stmt = $db->prepare($query);

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

            if($stmt->execute()) echo json_encode(array("message" => "Vehicle updated."));
            else { http_response_code(503); echo json_encode(array("message" => "Unable to update vehicle.")); }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Incomplete data."));
        }
        break;

    case 'DELETE':
        // Protected: Only Admin/Manager
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }

        if($id) {
            $query = "DELETE FROM vehicles WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $id);
            if($stmt->execute()) echo json_encode(array("message" => "Vehicle deleted."));
            else { http_response_code(503); echo json_encode(array("message" => "Unable to delete vehicle.")); }
        }
        break;
}
?>
