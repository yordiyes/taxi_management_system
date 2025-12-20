<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, PUT");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db.php';

$database = new Database();
$db = $database->getConnection();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(array("message" => "Unauthorized"));
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"));
$user_id = $_SESSION['user_id'];

switch ($method) {
    case 'GET':
        $query = "SELECT id, username, email, role, created_at FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            echo json_encode($user);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "User not found"));
        }
        break;

    case 'PUT':
        if (!empty($data->username) && !empty($data->email)) {
             // Check if email/username taken by OTHER user
            $check = "SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?";
            $stmtCheck = $db->prepare($check);
            $stmtCheck->bindParam(1, $data->email);
            $stmtCheck->bindParam(2, $data->username);
            $stmtCheck->bindParam(3, $user_id);
            $stmtCheck->execute();
            if ($stmtCheck->rowCount() > 0) {
                http_response_code(400);
                echo json_encode(array("message" => "Username or Email already taken"));
                exit();
            }

            $query = "UPDATE users SET username = :username, email = :email WHERE id = :id";
            $stmt = $db->prepare($query);
            
            $data->username = htmlspecialchars(strip_tags($data->username));
            $data->email = htmlspecialchars(strip_tags($data->email));

            $stmt->bindParam(':username', $data->username);
            $stmt->bindParam(':email', $data->email);
            $stmt->bindParam(':id', $user_id);

            if ($stmt->execute()) {
                // Update session
                $_SESSION['username'] = $data->username;
                echo json_encode(array("message" => "Profile updated successfully"));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Unable to update profile"));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Incomplete data"));
        }
        break;
}
?>
