<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"));

// Enforce Session Login for ALL booking actions
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized. Please login."));
    exit();
}

switch($method) {
    case 'GET':
        // If Admin/Manager, can view all or filter by user_id
        if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager') {
            if (isset($_GET['user_id'])) {
                $query = "SELECT b.*, s.name as service_name, d.name as driver_name, u.username 
                          FROM bookings b 
                          LEFT JOIN services s ON b.service_id = s.id 
                          LEFT JOIN drivers d ON b.driver_id = d.id 
                          LEFT JOIN users u ON b.user_id = u.id 
                          WHERE b.user_id = :user_id ORDER BY b.created_at DESC";
                $stmt = $db->prepare($query);
                $stmt->bindParam(":user_id", $_GET['user_id']);
            } else {
                $query = "SELECT b.*, u.username, s.name as service_name, d.name as driver_name 
                          FROM bookings b 
                          LEFT JOIN users u ON b.user_id = u.id 
                          LEFT JOIN services s ON b.service_id = s.id
                          LEFT JOIN drivers d ON b.driver_id = d.id
                          ORDER BY b.created_at DESC";
                $stmt = $db->prepare($query);
            }
        } else {
            // Customer: View ONLY their own bookings
            $query = "SELECT b.*, s.name as service_name, d.name as driver_name 
                      FROM bookings b 
                      LEFT JOIN services s ON b.service_id = s.id 
                      LEFT JOIN drivers d ON b.driver_id = d.id 
                      WHERE user_id = :user_id ORDER BY b.created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":user_id", $_SESSION['user_id']);
        }
        
        $stmt->execute();
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($bookings);
        break;

    case 'POST':
        if(!empty($data->pickup_location) && !empty($data->dropoff_location) && !empty($data->pickup_time)) {
            $query = "INSERT INTO bookings SET user_id=:user_id, service_id=:service_id, pickup_location=:pickup_location, dropoff_location=:dropoff_location, pickup_time=:pickup_time, status='pending'";
            $stmt = $db->prepare($query);

            // Use Session User ID
            $user_id = $_SESSION['user_id'];
            $service_id = isset($data->service_id) ? $data->service_id : null;
            $data->pickup_location = htmlspecialchars(strip_tags($data->pickup_location));
            $data->dropoff_location = htmlspecialchars(strip_tags($data->dropoff_location));
            $data->pickup_time = htmlspecialchars(strip_tags($data->pickup_time));

            $stmt->bindParam(":user_id", $user_id);
            $stmt->bindParam(":service_id", $service_id);
            $stmt->bindParam(":pickup_location", $data->pickup_location);
            $stmt->bindParam(":dropoff_location", $data->dropoff_location);
            $stmt->bindParam(":pickup_time", $data->pickup_time);

            if($stmt->execute()) {
                http_response_code(201);
                echo json_encode(array("message" => "Booking created."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to create booking."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Incomplete data."));
        }
        break;
}
?>
