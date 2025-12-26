<?php
// Consolidated CORS handler for all API endpoints - ALLOW EVERYTHING, NO BLOCKS
// Get the origin from the request - if present, use it (allows credentials), otherwise allow all
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';

// Allow all origins - use specific origin if provided (for credentials), otherwise wildcard
header("Access-Control-Allow-Origin: $origin");

// Allow all methods - literally everything
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS, HEAD, TRACE, CONNECT");

// Allow all headers - echo back whatever the client requests
$requestHeaders = isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']) 
    ? $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] 
    : 'Content-Type, Authorization, X-Requested-With, X-API-KEY, Accept, Origin, Cache-Control, Pragma, X-Custom-Header, *';
header("Access-Control-Allow-Headers: $requestHeaders");

// Allow credentials if origin is specified (required for credentials to work)
if ($origin !== '*') {
    header("Access-Control-Allow-Credentials: true");
}

// Expose all headers to the client
header("Access-Control-Expose-Headers: *");

// Cache preflight for 1 hour
header("Access-Control-Max-Age: 3600");

// Set content type
header("Content-Type: application/json; charset=UTF-8");

// Handle Preflight OPTIONS requests - return 200 immediately, no questions asked
if ($_SERVER['REQUEST_METHOD'] == "OPTIONS") {
    http_response_code(200);
    exit();
}
?>
