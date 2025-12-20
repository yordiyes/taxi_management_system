<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"));

switch($method) {
    case 'GET':
        $query = "SELECT * FROM vehicles";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($vehicles);
        break;

    case 'POST':
        if(!empty($data->make) && !empty($data->model) && !empty($data->license_plate) && !empty($data->type) && !empty($data->capacity)) {
            $query = "INSERT INTO vehicles SET make=:make, model=:model, license_plate=:license_plate, type=:type, capacity=:capacity, status=:status";
            $stmt = $db->prepare($query);

            $status = isset($data->status) ? $data->status : 'available';

            $stmt->bindParam(":make", $data->make);
            $stmt->bindParam(":model", $data->model);
            $stmt->bindParam(":license_plate", $data->license_plate);
            $stmt->bindParam(":type", $data->type);
            $stmt->bindParam(":capacity", $data->capacity);
            $stmt->bindParam(":status", $status);

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
        if(!empty($data->id)) {
            $query = "UPDATE vehicles SET make=:make, model=:model, license_plate=:license_plate, type=:type, capacity=:capacity, status=:status WHERE id=:id";
            $stmt = $db->prepare($query);

            $stmt->bindParam(":make", $data->make);
            $stmt->bindParam(":model", $data->model);
            $stmt->bindParam(":license_plate", $data->license_plate);
            $stmt->bindParam(":type", $data->type);
            $stmt->bindParam(":capacity", $data->capacity);
            $stmt->bindParam(":status", $data->status);
            $stmt->bindParam(":id", $data->id);

            if($stmt->execute()) {
                echo json_encode(array("message" => "Vehicle updated."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to update vehicle."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "ID is missing."));
        }
        break;

    case 'DELETE':
        if(!empty($data->id)) {
            $query = "DELETE FROM vehicles WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":id", $data->id);

            if($stmt->execute()) {
                echo json_encode(array("message" => "Vehicle deleted."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to delete vehicle."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "ID is missing."));
        }
        break;
}
?>
