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
        // Public API
        if($id) {
            $query = "SELECT * FROM drivers WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $id);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if($row) echo json_encode($row);
            else { http_response_code(404); echo json_encode(array("message" => "Driver not found.")); }
        } else {
            $query = "SELECT d.*, v.make, v.model FROM drivers d LEFT JOIN vehicles v ON d.vehicle_id = v.id ORDER BY d.created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $drivers = array();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) array_push($drivers, $row);
            echo json_encode($drivers);
        }
        break;

    case 'POST':
        // Protected: Only Admin/Manager
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }

        if(!empty($data->name) && !empty($data->license_number)) {
            $query = "INSERT INTO drivers SET name=:name, license_number=:license_number, phone=:phone, vehicle_id=:vehicle_id, status=:status";
            $stmt = $db->prepare($query);

            $data->name = htmlspecialchars(strip_tags($data->name));
            $data->license_number = htmlspecialchars(strip_tags($data->license_number));
            $data->phone = htmlspecialchars(strip_tags($data->phone));
            $data->vehicle_id = !empty($data->vehicle_id) ? $data->vehicle_id : null;
            $data->status = isset($data->status) ? $data->status : 'available';

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
        break;

    case 'PUT':
        // Protected: Only Admin/Manager
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }

        if($id && !empty($data->name)) {
            $query = "UPDATE drivers SET name=:name, license_number=:license_number, phone=:phone, vehicle_id=:vehicle_id, status=:status WHERE id=:id";
            $stmt = $db->prepare($query);

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

            if($stmt->execute()) echo json_encode(array("message" => "Driver updated."));
            else { http_response_code(503); echo json_encode(array("message" => "Unable to update driver.")); }
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
            $query = "DELETE FROM drivers WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $id);
            if($stmt->execute()) echo json_encode(array("message" => "Driver deleted."));
            else { http_response_code(503); echo json_encode(array("message" => "Unable to delete driver.")); }
        }
        break;
}
?>
