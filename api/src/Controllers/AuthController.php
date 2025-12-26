<?php
namespace Api\Controllers;

use PDO;

class AuthController {
    private $db;
    private $action;

    public function __construct($db, $action) {
        $this->db = $db;
        $this->action = $action;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function processRequest() {
        switch ($this->action) {
            case 'register':
                $this->register();
                break;
            case 'login':
                $this->login();
                break;
            case 'logout':
                $this->logout();
                break;
            case 'check_session':
                $this->checkSession();
                break;
            default:
                http_response_code(400);
                echo json_encode(array("message" => "Invalid action."));
                break;
        }
    }

    private function register() {
        $data = json_decode(file_get_contents("php://input"));
        if(
            !empty($data->username) &&
            !empty($data->email) &&
            !empty($data->password)
        ){
            // Check if email already exists
            $check_query = "SELECT id FROM users WHERE email = ? OR username = ?";
            $check_stmt = $this->db->prepare($check_query);
            $check_stmt->bindParam(1, $data->email);
            $check_stmt->bindParam(2, $data->username);
            $check_stmt->execute();
            
            if($check_stmt->rowCount() > 0){
                 http_response_code(400);
                 echo json_encode(array("message" => "Username or Email already exists."));
                 exit();
            }

            $query = "INSERT INTO users SET username=:username, email=:email, password=:password, role=:role";
            $stmt = $this->db->prepare($query);

            $data->username = htmlspecialchars(strip_tags($data->username));
            $data->email = htmlspecialchars(strip_tags($data->email));
            $data->password = htmlspecialchars(strip_tags($data->password));
            $role = isset($data->role) ? $data->role : 'customer'; // Default to customer

            $password_hash = password_hash($data->password, PASSWORD_BCRYPT);

            $stmt->bindParam(":username", $data->username);
            $stmt->bindParam(":email", $data->email);
            $stmt->bindParam(":password", $password_hash);
            $stmt->bindParam(":role", $role);

            if($stmt->execute()){
                $user_id = $this->db->lastInsertId();
                
                // If registering as driver, create driver record
                if ($role === 'driver') {
                    if (!empty($data->license_number) && !empty($data->phone)) {
                        $driver_query = "INSERT INTO drivers SET user_id=:user_id, name=:name, license_number=:license_number, phone=:phone, status='available'";
                        $driver_stmt = $this->db->prepare($driver_query);
                        
                        $driver_name = htmlspecialchars(strip_tags($data->username));
                        $driver_license = htmlspecialchars(strip_tags($data->license_number));
                        $driver_phone = htmlspecialchars(strip_tags($data->phone));
                        
                        $driver_stmt->bindParam(":user_id", $user_id);
                        $driver_stmt->bindParam(":name", $driver_name);
                        $driver_stmt->bindParam(":license_number", $driver_license);
                        $driver_stmt->bindParam(":phone", $driver_phone);
                        
                        if (!$driver_stmt->execute()) {
                            // If driver creation fails, we should ideally rollback user creation
                            // But for simplicity, we'll just log the error
                            error_log("Failed to create driver record for user_id: " . $user_id);
                        }
                    }
                }
                
                http_response_code(201);
                echo json_encode(array("message" => "User was registered."));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to register user."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Unable to register user. Data is incomplete."));
        }
    }

    private function login() {
        $data = json_decode(file_get_contents("php://input"));
        if(
            !empty($data->email) &&
            !empty($data->password)
        ){
            $query = "SELECT id, username, password, role FROM users WHERE email = ? LIMIT 0,1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(1, $data->email);
            $stmt->execute();

            if($stmt->rowCount() > 0){
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if(password_verify($data->password, $row['password'])){
                    
                    // Set Session
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = $row['role'];

                    http_response_code(200);
                    echo json_encode(array(
                        "message" => "Login successful.",
                        "user" => array(
                            "id" => $row['id'],
                            "username" => $row['username'],
                            "role" => $row['role']
                        )
                    ));
                } else {
                    http_response_code(401);
                    echo json_encode(array("message" => "Login failed. Incorrect password."));
                }
            } else {
                http_response_code(401);
                echo json_encode(array("message" => "Login failed. User not found."));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Unable to login. Data is incomplete."));
        }
    }

    private function logout() {
        session_destroy();
        http_response_code(200);
        echo json_encode(array("message" => "Logout successful."));
    }

    private function checkSession() {
        if (isset($_SESSION['user_id'])) {
            http_response_code(200);
            echo json_encode(array(
                "user" => array(
                    "id" => $_SESSION['user_id'],
                    "username" => $_SESSION['username'],
                    "role" => $_SESSION['role']
                )
            ));
        } else {
            http_response_code(401);
            echo json_encode(array("message" => "No active session."));
        }
    }
}
?>
