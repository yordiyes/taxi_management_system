<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-API-KEY");

require_once 'db.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
if ($method == "OPTIONS") {
    http_response_code(200);
    exit();
}

$id = isset($_GET['id']) ? $_GET['id'] : null;
$data = json_decode(file_get_contents("php://input"));

switch($method) {
    case 'GET':
        // Public API: Anyone can view services
        if($id) {
            $query = "SELECT * FROM services WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->bindParam(1, $id);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if($row) {
                echo json_encode($row);
            } else {
                http_response_code(404);
                echo json_encode(array("message" => "Service not found."));
            }
        } else {
            $query = "SELECT * FROM services ORDER BY created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $services = array();
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
                array_push($services, $row);
            }
            echo json_encode($services);
        }
        break;

    case 'POST':
        // Protected: Only Admin/Manager
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }

        if(!empty($data->name) && !empty($data->base_price) && !empty($data->price_per_km)) {
            $query = "INSERT INTO services SET name=:name, description=:description, base_price=:base_price, price_per_km=:price_per_km";
            $stmt = $db->prepare($query);

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
        break;

    case 'PUT':
        // Protected: Only Admin/Manager
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }

        if($id && !empty($data->name)) {
            $query = "UPDATE services SET name=:name, description=:description, base_price=:base_price, price_per_km=:price_per_km WHERE id=:id";
            $stmt = $db->prepare($query);

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
        break;

    case 'DELETE':
        // Protected: Only Admin/Manager
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }

        if($id) {
            $query = "DELETE FROM services WHERE id = ?";
            $stmt = $db->prepare($query);
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
        break;
}
?>
