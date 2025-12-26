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
    $action = $_GET['action'] ?? 'create_reservation'; // Default action if not specified

    // Map field names to match external API expectations
    if ($action === 'create_reservation') {
        // Transform field names to match external API
        $mappedPayload = [];
        
        // Required fields mapping
        if (isset($payload['restaurant_id'])) {
            $mappedPayload['restaurant_id'] = $payload['restaurant_id'];
        }
        if (isset($payload['reservation_date'])) {
            $mappedPayload['date'] = $payload['reservation_date'];
        }
        if (isset($payload['reservation_time'])) {
            $mappedPayload['time'] = $payload['reservation_time'];
        }
        if (isset($payload['party_size'])) {
            $mappedPayload['party_size'] = $payload['party_size'];
        }
        if (isset($payload['customer_name'])) {
            $mappedPayload['customer_name'] = $payload['customer_name'];
        }
        if (isset($payload['customer_email'])) {
            $mappedPayload['customer_email'] = $payload['customer_email'];
        }
        if (isset($payload['customer_phone'])) {
            $mappedPayload['customer_phone'] = $payload['customer_phone'];
        }
        if (isset($payload['special_requests'])) {
            $mappedPayload['special_requests'] = $payload['special_requests'];
        }
        
        // Keep any other fields as-is
        foreach ($payload as $key => $value) {
            if (!isset($mappedPayload[$key]) && !in_array($key, ['reservation_date', 'reservation_time'])) {
                $mappedPayload[$key] = $value;
            }
        }
        
        $payload = $mappedPayload;
    }

    $url = $RESTAURANT_API_BASE . '?action=' . urlencode($action);
    
    $response = ExternalService::requestJson($url, 'POST', $payload, $headers);

    if ($response['ok']) {
        $data = $response['data'];
        
        // Ensure consistent response format for bookings
        // If the external API returns data directly, wrap it properly
        if (isset($data['status']) && isset($data['data'])) {
            // Already in correct format, return as-is
            echo json_encode($data);
        } else if (isset($data['status']) && ($data['status'] === 'success' || $data['status'] === 'error')) {
            // Has status field, return as-is
            echo json_encode($data);
        } else if (is_array($data) && !isset($data['status'])) {
            // Direct response without status, wrap it
            echo json_encode([
                'status' => 'success',
                'message' => 'Reservation created successfully',
                'data' => $data
            ]);
        } else {
            // Return as-is if already properly formatted
            echo json_encode($data);
        }
    } else {
        http_response_code($response['status'] ?: 500);
        $errorData = $response['data'] ?? null;
        $errorMessage = $response['error'] ?? 'External service error';
        
        // Extract error message from nested error structure if present
        if (is_array($errorData) && isset($errorData['error']['message'])) {
            $errorMessage = $errorData['error']['message'];
        } else if (is_array($errorData) && isset($errorData['message'])) {
            $errorMessage = $errorData['message'];
        }
        
        echo json_encode([
            'status' => 'error',
            'message' => $errorMessage,
            'details' => $errorData
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
