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

// Auth for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::authenticate();
}

$RESTAURANT_API_BASE = 'https://restaurant-management.page.gd/api/service-provider.php';
$RESTAURANT_API_KEY = 'TAXI_SERVICE_KEY_2025';

$headers = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers[] = 'X-API-Key: ' . $RESTAURANT_API_KEY;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents('php://input'), true);
    $action = $_GET['action'] ?? 'book_table'; // Default action if not specified

    $url = $RESTAURANT_API_BASE . '?action=' . urlencode($action);
    
    $response = ExternalService::requestJson($url, 'POST', $payload, $headers);

    if ($response['ok']) {
        echo json_encode($response['data']);
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

// Handle GET
$queryParams = $_GET;
$url = $RESTAURANT_API_BASE;

if (!empty($queryParams)) {
    $url .= '?' . http_build_query($queryParams);
}

$response = ExternalService::requestJson($url, 'GET', null, $headers);

if ($response['ok']) {
    $data = $response['data'];
    
    // Ensure consistent response format
    // If the external API returns data directly, wrap it properly
    if (isset($data['status']) && isset($data['data'])) {
        // Already in correct format, return as-is
        echo json_encode($data);
    } else if (isset($data['status']) && isset($data['restaurants'])) {
        // Has status but restaurants array, ensure data field exists
        echo json_encode([
            'status' => $data['status'],
            'message' => $data['message'] ?? 'Restaurants retrieved successfully',
            'data' => $data['restaurants'] ?? $data
        ]);
    } else if (is_array($data) && !isset($data['status'])) {
        // Direct array of restaurants, wrap it
        echo json_encode([
            'status' => 'success',
            'message' => 'Restaurants retrieved successfully',
            'data' => $data
        ]);
    } else {
        // Return as-is if already properly formatted
        echo json_encode($data);
    }
} else {
    http_response_code($response['status'] ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $response['error'] ?: 'External service error'
    ]);
}
