<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if (!isset($_GET['action'])) {
    http_response_code(400);
    echo json_encode(array("message" => "Action parameter is missing."));
    exit();
}

$action = $_GET['action'];

if ($action == 'register') {
    if(
        !empty($data->username) &&
        !empty($data->email) &&
        !empty($data->password)
    ){
        // Check if email already exists
        $check_query = "SELECT id FROM users WHERE email = ? OR username = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(1, $data->email);
        $check_stmt->bindParam(2, $data->username);
        $check_stmt->execute();
        
        if($check_stmt->rowCount() > 0){
             http_response_code(400);
             echo json_encode(array("message" => "Username or Email already exists."));
             exit();
        }

        $query = "INSERT INTO users SET username=:username, email=:email, password=:password, role=:role";
        $stmt = $db->prepare($query);

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
} elseif ($action == 'login') {
    if(
        !empty($data->email) &&
        !empty($data->password)
    ){
        $query = "SELECT id, username, password, role FROM users WHERE email = ? LIMIT 0,1";
        $stmt = $db->prepare($query);
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
} elseif ($action == 'logout') {
    session_destroy();
    http_response_code(200);
    echo json_encode(array("message" => "Logout successful."));
} elseif ($action == 'check_session') {
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
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid action."));
}
?>
