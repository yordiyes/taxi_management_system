<?php
namespace Api\Controllers;

use PDO;

class ProfileController {
    private $db;
    private $requestMethod;
    private $userId;

    public function __construct($db, $requestMethod) {
        $this->db = $db;
        $this->requestMethod = $requestMethod;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(array("message" => "Unauthorized"));
            exit();
        }
        $this->userId = $_SESSION['user_id'];
    }

    public function processRequest() {
        switch ($this->requestMethod) {
            case 'GET':
                $this->getProfile();
                break;
            case 'PUT':
                $this->updateProfile();
                break;
            default:
                http_response_code(405);
                echo json_encode(array("message" => "Method Not Allowed"));
                break;
        }
    }

    private function getProfile() {
        $query = "SELECT id, username, email, role, created_at FROM users WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(1, $this->userId);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            echo json_encode($user);
        } else {
            http_response_code(404);
            echo json_encode(array("message" => "User not found"));
        }
    }

    private function updateProfile() {
        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->username) && !empty($data->email)) {
             // Check if email/username taken by OTHER user
            $check = "SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?";
            $stmtCheck = $this->db->prepare($check);
            $stmtCheck->bindParam(1, $data->email);
            $stmtCheck->bindParam(2, $data->username);
            $stmtCheck->bindParam(3, $this->userId);
            $stmtCheck->execute();
            if ($stmtCheck->rowCount() > 0) {
                http_response_code(400);
                echo json_encode(array("message" => "Username or Email already taken"));
                exit();
            }

            $query = "UPDATE users SET username = :username, email = :email WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            $data->username = htmlspecialchars(strip_tags($data->username));
            $data->email = htmlspecialchars(strip_tags($data->email));

            $stmt->bindParam(':username', $data->username);
            $stmt->bindParam(':email', $data->email);
            $stmt->bindParam(':id', $this->userId);

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
    }
}
?>
