<?php
namespace Api\Middleware;

class Auth {
    public static function authenticate() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['message' => 'Unauthorized. Please login.']);
            exit;
        }
    }
}
