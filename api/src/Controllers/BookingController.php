<?php
namespace Api\Controllers;

use PDO;

class BookingController {
    private $db;
    private $requestMethod;
    private $bookingId;

    public function __construct($db, $requestMethod, $bookingId) {
        $this->db = $db;
        $this->requestMethod = $requestMethod;
        $this->bookingId = $bookingId;
    }

    public function processRequest() {
        switch ($this->requestMethod) {
            case 'GET':
                $this->getBookings();
                break;
            case 'POST':
                $this->createBooking();
                break;
            case 'PUT':
                $this->updateBooking($this->bookingId);
                break;
            default:
                $this->notFoundResponse();
                break;
        }
    }

    private function getBookings() {
        $this->checkSession();
        
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

        $filter_user_id = null;
        if (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'manager')) {
            if (isset($_GET['user_id'])) {
                $filter_user_id = $_GET['user_id'];
            } elseif (isset($_GET['email'])) {
                // Lookup by email if provided
                $u_stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
                $u_stmt->execute([$_GET['email']]);
                $u_row = $u_stmt->fetch(PDO::FETCH_ASSOC);
                $filter_user_id = $u_row ? $u_row['id'] : -1; // -1 ensures no results if user not found
            }
        } elseif (isset($_SESSION['user_id'])) {
            $filter_user_id = $_SESSION['user_id'];
        } elseif ($is_api_call && isset($_GET['email'])) {
            // External API call filtering by email
            $u_stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $u_stmt->execute([$_GET['email']]);
            $u_row = $u_stmt->fetch(PDO::FETCH_ASSOC);
            $filter_user_id = $u_row ? $u_row['id'] : -1;
        }

        // If driver, show bookings they can take or have taken
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver') {
            $driver_query = "SELECT id FROM drivers WHERE user_id = ?";
            $driver_stmt = $this->db->prepare($driver_query);
            $driver_stmt->bindParam(1, $_SESSION['user_id']);
            $driver_stmt->execute();
            $driver_row = $driver_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($driver_row) {
                $query = "SELECT b.*, u.username, s.name as service_name, d.name as driver_name 
                          FROM bookings b 
                          LEFT JOIN users u ON b.user_id = u.id 
                          LEFT JOIN services s ON b.service_id = s.id
                          LEFT JOIN drivers d ON b.driver_id = d.id
                          WHERE b.driver_id = :driver_id OR (b.driver_id IS NULL AND b.status = 'pending')
                          ORDER BY b.created_at DESC";
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(":driver_id", $driver_row['id']);
                $stmt->execute();
                $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($bookings);
                return;
            }
        }
        
        if ($filter_user_id !== null) {
            $query = "SELECT b.*, s.name as service_name, d.name as driver_name, u.username 
                      FROM bookings b 
                      LEFT JOIN services s ON b.service_id = s.id 
                      LEFT JOIN drivers d ON b.driver_id = d.id 
                      LEFT JOIN users u ON b.user_id = u.id 
                      WHERE b.user_id = :user_id ORDER BY b.created_at DESC";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(":user_id", $filter_user_id);
        } else {
            // Admin/Manager view all
            $query = "SELECT b.*, u.username, s.name as service_name, d.name as driver_name 
                      FROM bookings b 
                      LEFT JOIN users u ON b.user_id = u.id 
                      LEFT JOIN services s ON b.service_id = s.id
                      LEFT JOIN drivers d ON b.driver_id = d.id
                      ORDER BY b.created_at DESC";
            $stmt = $this->db->prepare($query);
        }
        
        $stmt->execute();
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($bookings);
    }

    private function createBooking() {
        $this->checkSession();
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

        if(!empty($data->pickup_location) && !empty($data->dropoff_location) && !empty($data->pickup_time)) {
            $query = "INSERT INTO bookings SET user_id=:user_id, service_id=:service_id, pickup_location=:pickup_location, dropoff_location=:dropoff_location, pickup_time=:pickup_time, status='pending'";
            $stmt = $this->db->prepare($query);

            // Determine User ID
            if ($is_api_call) {
                if (!empty($data->user_id)) {
                    $user_id = $data->user_id;
                } elseif (!empty($data->email)) {
                    // SEAMLESS INTEGRATION: Find or create user by email
                    $check_user = "SELECT id FROM users WHERE email = ?";
                    $check_stmt = $this->db->prepare($check_user);
                    $check_stmt->bindParam(1, $data->email);
                    $check_stmt->execute();
                    $user_row = $check_stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user_row) {
                        $user_id = $user_row['id'];
                    } else {
                        // Auto-register a minimal user account
                        $new_username = explode('@', $data->email)[0] . '_' . rand(100, 999);
                        $reg_query = "INSERT INTO users SET username=:username, email=:email, password=:password, role='customer'";
                        $reg_stmt = $this->db->prepare($reg_query);
                        $dummy_pass = password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT);
                        
                        $reg_stmt->bindParam(":username", $new_username);
                        $reg_stmt->bindParam(":email", $data->email);
                        $reg_stmt->bindParam(":password", $dummy_pass);
                        $reg_stmt->execute();
                        $user_id = $this->db->lastInsertId();
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
    }

    private function updateBooking($id) {
        $this->checkSession();
        $data = json_decode(file_get_contents("php://input"));

        // Protected: Admin/Manager/Driver
        if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager' && $_SESSION['role'] !== 'driver')) {
            http_response_code(403);
            echo json_encode(array("message" => "Access Denied."));
            exit();
        }

        if($id && !empty($data->status)) {
            // If driver is confirming, assign themselves and their vehicle
            $driver_id = null;
            $vehicle_id = null;
            
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'driver' && $data->status === 'confirmed') {
                // Get driver record
                $driver_query = "SELECT id, vehicle_id FROM drivers WHERE user_id = ?";
                $driver_stmt = $this->db->prepare($driver_query);
                $driver_stmt->bindParam(1, $_SESSION['user_id']);
                $driver_stmt->execute();
                $driver_row = $driver_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($driver_row) {
                    $driver_id = $driver_row['id'];
                    $vehicle_id = $driver_row['vehicle_id'];
                    
                    // Update vehicle status to booked
                    if ($vehicle_id) {
                        $vehicle_update = "UPDATE vehicles SET status = 'booked' WHERE id = ?";
                        $vehicle_stmt = $this->db->prepare($vehicle_update);
                        $vehicle_stmt->bindParam(1, $vehicle_id);
                        $vehicle_stmt->execute();
                    }
                    
                    // Update driver status
                    $driver_status_update = "UPDATE drivers SET status = 'on_trip' WHERE id = ?";
                    $driver_status_stmt = $this->db->prepare($driver_status_update);
                    $driver_status_stmt->bindParam(1, $driver_id);
                    $driver_status_stmt->execute();
                } else {
                    http_response_code(403);
                    echo json_encode(array("message" => "Driver profile not found."));
                    exit();
                }
            }
            
            // If completing, free up vehicle and driver
            if (isset($data->status) && $data->status === 'completed') {
                // Get booking to find driver
                $booking_query = "SELECT driver_id FROM bookings WHERE id = ?";
                $booking_stmt = $this->db->prepare($booking_query);
                $booking_stmt->bindParam(1, $id);
                $booking_stmt->execute();
                $booking_row = $booking_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($booking_row && $booking_row['driver_id']) {
                    // Get vehicle_id from driver
                    $driver_vehicle_query = "SELECT vehicle_id FROM drivers WHERE id = ?";
                    $driver_vehicle_stmt = $this->db->prepare($driver_vehicle_query);
                    $driver_vehicle_stmt->bindParam(1, $booking_row['driver_id']);
                    $driver_vehicle_stmt->execute();
                    $driver_vehicle_row = $driver_vehicle_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($driver_vehicle_row && $driver_vehicle_row['vehicle_id']) {
                        // Free vehicle
                        $vehicle_free = "UPDATE vehicles SET status = 'available' WHERE id = ?";
                        $vehicle_free_stmt = $this->db->prepare($vehicle_free);
                        $vehicle_free_stmt->bindParam(1, $driver_vehicle_row['vehicle_id']);
                        $vehicle_free_stmt->execute();
                    }
                    
                    // Free driver
                    $driver_free = "UPDATE drivers SET status = 'available' WHERE id = ?";
                    $driver_free_stmt = $this->db->prepare($driver_free);
                    $driver_free_stmt->bindParam(1, $booking_row['driver_id']);
                    $driver_free_stmt->execute();
                }
            }

            $query = "UPDATE bookings SET status=:status" . ($driver_id ? ", driver_id=:driver_id" : "") . " WHERE id=:id";
            $stmt = $this->db->prepare($query);

            $data->status = htmlspecialchars(strip_tags($data->status));

            $stmt->bindParam(":status", $data->status);
            if ($driver_id) {
                $stmt->bindParam(":driver_id", $driver_id);
            }
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
