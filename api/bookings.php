<?php
require_once 'cors.php';
require_once 'db.php';

$database = new Database();
$db = $database->getConnection();

require_once 'config.php'; // Ensure config is loaded for API_KEY

$data = json_decode(file_get_contents("php://input"));

// Auth Check: Session OR API Key
$is_api_call = false;
$headers = getallheaders();
$api_key = isset($headers['X-API-KEY']) ? $headers['X-API-KEY'] : (isset($_GET['api_key']) ? $_GET['api_key'] : null);

if ($api_key === API_KEY) {
    $is_api_call = true;
} elseif (isset($_SESSION['user_id'])) {
    // Standard session login
} else {
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized. Please login or provide valid API Key."));
    exit();
}

switch($method) {
    case 'GET':
        // If Admin/Manager, can view all or filter by user_id
        // Determine filtering
        $filter_user_id = null;
        if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager') {
            if (isset($_GET['user_id'])) {
                $filter_user_id = $_GET['user_id'];
            } elseif (isset($_GET['email'])) {
                // Lookup by email if provided
                $u_stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                $u_stmt->execute([$_GET['email']]);
                $u_row = $u_stmt->fetch(PDO::FETCH_ASSOC);
                $filter_user_id = $u_row ? $u_row['id'] : -1; // -1 ensures no results if user not found
            }
        } elseif (isset($_SESSION['user_id'])) {
            $filter_user_id = $_SESSION['user_id'];
        } elseif ($is_api_call && isset($_GET['email'])) {
            // External API call filtering by email
            $u_stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $u_stmt->execute([$_GET['email']]);
            $u_row = $u_stmt->fetch(PDO::FETCH_ASSOC);
            $filter_user_id = $u_row ? $u_row['id'] : -1;
        }

        if ($filter_user_id !== null) {
            $query = "SELECT b.*, s.name as service_name, d.name as driver_name, u.username 
                      FROM bookings b 
                      LEFT JOIN services s ON b.service_id = s.id 
                      LEFT JOIN drivers d ON b.driver_id = d.id 
                      LEFT JOIN users u ON b.user_id = u.id 
                      WHERE b.user_id = :user_id ORDER BY b.created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":user_id", $filter_user_id);
        } else {
            // Admin/Manager view all
            $query = "SELECT b.*, u.username, s.name as service_name, d.name as driver_name 
                      FROM bookings b 
                      LEFT JOIN users u ON b.user_id = u.id 
                      LEFT JOIN services s ON b.service_id = s.id
                      LEFT JOIN drivers d ON b.driver_id = d.id
                      ORDER BY b.created_at DESC";
            $stmt = $db->prepare($query);
        }
        
        $stmt->execute();
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($bookings);
        break;

    case 'POST':
        if(!empty($data->pickup_location) && !empty($data->dropoff_location) && !empty($data->pickup_time)) {
            $query = "INSERT INTO bookings SET user_id=:user_id, service_id=:service_id, pickup_location=:pickup_location, dropoff_location=:dropoff_location, pickup_time=:pickup_time, status='pending'";
            $stmt = $db->prepare($query);

            // Determine User ID
            if ($is_api_call) {
                if (!empty($data->user_id)) {
                    $user_id = $data->user_id;
                } elseif (!empty($data->email)) {
                    // SEAMLESS INTEGRATION: Find or create user by email
                    $check_user = "SELECT id FROM users WHERE email = ?";
                    $check_stmt = $db->prepare($check_user);
                    $check_stmt->bindParam(1, $data->email);
                    $check_stmt->execute();
                    $user_row = $check_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user_row) {
                        $user_id = $user_row['id'];
                    } else {
                        // Auto-register a minimal user account
                        $new_username = explode('@', $data->email)[0] . '_' . rand(100, 999);
                        $reg_query = "INSERT INTO users SET username=:username, email=:email, password=:password, role='customer'";
                        $reg_stmt = $db->prepare($reg_query);
                        $dummy_pass = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
                        
                        $reg_stmt->bindParam(":username", $new_username);
                        $reg_stmt->bindParam(":email", $data->email);
                        $reg_stmt->bindParam(":password", $dummy_pass);
                        $reg_stmt->execute();
                        $user_id = $db->lastInsertId();
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(array("message" => "External Booking requires user_id or email."));
                    exit();
                }
            } else {
                $user_id = $_SESSION['user_id'];
            }
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
    case 'PUT':
        // Protected: Only Admin/Manager
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }

        $id = isset($_GET['id']) ? $_GET['id'] : null;

        if($id && !empty($data->status)) {
            $query = "UPDATE bookings SET status=:status WHERE id=:id";
            $stmt = $db->prepare($query);

            $data->status = htmlspecialchars(strip_tags($data->status));

            $stmt->bindParam(":status", $data->status);
            $stmt->bindParam(":id", $id);

            if($stmt->execute()) {
                echo json_encode(array("message" => "Booking updated."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to update booking."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Incomplete data."));
        }
        break;
}
?>
