<?php
// Verification Script for Taxi Management System API

$baseUrl = 'http://localhost/taxi_management_system/api';
$cookieFile = 'cookie.txt';

if (file_exists($cookieFile)) unlink($cookieFile);

function makeRequest($url, $method = 'GET', $data = null, $cookies = null) {
    $options = [
        'http' => [
            'header'  => "Content-type: application/json\r\n",
            'method'  => $method,
            'ignore_errors' => true
        ]
    ];

    if ($cookies) {
        $options['http']['header'] .= "Cookie: $cookies\r\n";
    }

    if ($data) {
        $options['http']['content'] = json_encode($data);
    }

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    // Parse response headers for cookies
    $responseCookies = [];
    foreach ($http_response_header as $hdr) {
        if (preg_match('/^Set-Cookie:\s*([^;]+)/', $hdr, $matches)) {
            $responseCookies[] = $matches[1];
        }
    }
    
    // Parse status code
    preg_match('#HTTP/\d+\.\d+ (\d+)#', $http_response_header[0], $matches);
    $statusCode = intval($matches[1]);

    return [
        'code' => $statusCode,
        'body' => json_decode($result, true),
        'cookies' => $responseCookies
    ];
}

echo "Starting Verification...\n------------------------\n";

// 1. Test Public API (Services GET)
echo "1. Testing Public API (GET services.php)... ";
$res = makeRequest("$baseUrl/services.php");
if ($res['code'] === 200 && is_array($res['body'])) {
    echo "PASSED (Code: 200, Found " . count($res['body']) . " services)\n";
} else {
    echo "FAILED (Code: {$res['code']})\n";
}

// 2. Test Registration
$testUser = 'verify_user_' . rand(1000, 9999);
$testEmail = $testUser . '@test.com';
// Generate a unique, non-hardcoded password for this verification run
$password = bin2hex(random_bytes(8));

echo "2. Testing Registration ($testUser)... ";
$res = makeRequest("$baseUrl/auth.php?action=register", 'POST', [
    'username' => $testUser,
    'email' => $testEmail,
    'password' => $password,
    'role' => 'customer'
]);

if ($res['code'] === 201) {
    echo "PASSED (User Created)\n";
} else {
    echo "FAILED (Code: {$res['code']}, Msg: " . ($res['body']['message'] ?? 'Unknown') . ")\n";
    // If user exists, try login anyway
    if ($res['code'] === 400) echo "   (User might already exist, proceeding...)\n";
}

// 3. Test Login
echo "3. Testing Login... ";
$res = makeRequest("$baseUrl/auth.php?action=login", 'POST', [
    'email' => $testEmail,
    'password' => $password
]);

$sessionCookie = '';
if ($res['code'] === 200 && !empty($res['cookies'])) {
    $sessionCookie = implode('; ', $res['cookies']);
    echo "PASSED (Logged in, Cookie: " . substr($sessionCookie, 0, 20) . "...)\n";
} else {
    echo "FAILED (Code: {$res['code']})\n";
    exit("Cannot proceed without login.\n");
}

// 4. Test RBAC (Customer trying to CREATE Service - Should FAIL)
echo "4. Testing RBAC (Customer vs Admin)... ";
$res = makeRequest("$baseUrl/services.php", 'POST', [
    'name' => 'Hacker Service',
    'base_price' => 1,
    'price_per_km' => 1
], $sessionCookie);

if ($res['code'] === 403) {
    echo "PASSED (Correctly Denied 403)\n";
} else {
    echo "FAILED (Expected 403, Got {$res['code']})\n";
}

// 5. Test Profile (Should Succeed)
echo "5. Testing Profile Access... ";
$res = makeRequest("$baseUrl/profile.php", 'GET', null, $sessionCookie);

if ($res['code'] === 200 && $res['body']['username'] === $testUser) {
    echo "PASSED (Retrieved Profile: {$res['body']['username']})\n";
} else {
    echo "FAILED (Code: {$res['code']})\n";
}

echo "------------------------\nVerification Complete.\n";
?>
