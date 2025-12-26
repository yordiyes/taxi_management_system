<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-KEY');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once 'src/Middleware/Auth.php';
require_once 'src/Helpers/ExternalService.php';

use Api\Middleware\Auth;
use Api\Helpers\ExternalService;

// Enforce API key authentication or User Session
// For this public-facing proxy, we might want to allow public access for GET (viewing hotels)
// but require Auth for POST (booking).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::authenticate();
}

$HOTEL_API_BASE = 'https://hotelmanagemt.infinityfreeapp.com/api';
$HOTEL_API_KEY = 'HOTEL_GROUP6_API_KEY_2024';

$hotelHeaders = [
    'X-API-Key: ' . $HOTEL_API_KEY
];

// Handle POST (Booking)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!$payload) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid JSON payload']);
        exit;
    }

    // Validate required fields
    if (empty($payload['hotel_id']) || empty($payload['room_id']) || empty($payload['check_in']) || empty($payload['check_out'])) {
        http_response_code(400);
        echo json_encode(['message' => 'hotel_id, room_id, check_in, and check_out are required']);
        exit;
    }

    // Add user details from session if not provided (optional enhancement)
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($payload['guest_name']) && isset($_SESSION['username'])) {
        $payload['guest_name'] = $_SESSION['username'];
    }

    $url = $HOTEL_API_BASE . '/bookings.php';
    $response = ExternalService::requestJson($url, 'POST', $payload, $hotelHeaders);

    if ($response['ok']) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Hotel booking confirmed',
            'data' => $response['data']['data'] ?? $response['data']
        ]);
    } else {
        http_response_code($response['status'] ?: 500);
        echo json_encode([
            'status' => 'error',
            'message' => $response['error'] ?: 'External service error',
            'details' => $response['data']
        ]);
    }
    exit;
}

// Handle GET (Search/List)
$endpoint = '/hotels.php'; // Default
$queryParams = $_GET;

// If hotel_id is provided, we are likely looking for rooms
if (isset($queryParams['hotel_id']) && !isset($queryParams['endpoint'])) {
    $endpoint = '/rooms.php';
}

// Allow explicit endpoint override
if (isset($queryParams['endpoint'])) {
    $endpoint = '/' . ltrim($queryParams['endpoint'], '/');
    unset($queryParams['endpoint']);
}

$url = $HOTEL_API_BASE . $endpoint;

// Append query params
if (!empty($queryParams)) {
    $url .= '?' . http_build_query($queryParams);
}

$response = ExternalService::requestJson($url, 'GET', null, $hotelHeaders);

if ($response['ok']) {
    echo json_encode([
        'status' => 'success',
        'data' => $response['data']['data'] ?? $response['data']
    ]);
} else {
    http_response_code($response['status'] ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $response['error'] ?: 'External service error'
    ]);
}
