<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once 'db.php';

$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Protected: Only Admin/Manager
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'manager')) {
    http_response_code(403);
    echo json_encode(array("message" => "Access Denied."));
    exit();
}

if ($action == 'stats') {
    // Visitor Statistics (Mocked/Calculated)
    $stats = array();

    // Count Users
    $query = "SELECT COUNT(*) as total_users FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];

    // Count Bookings
    $query = "SELECT COUNT(*) as total_bookings FROM bookings";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_bookings'];

    // Count Services
    $query = "SELECT COUNT(*) as total_services FROM services";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_services'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_services'];

    // Mock Visitor Count (Random for demo)
    $stats['visitors_today'] = rand(10, 100);

    echo json_encode($stats);

} elseif ($action == 'history') {
    // Booking History
    $query = "SELECT b.*, u.username, s.name as service_name 
              FROM bookings b 
              LEFT JOIN users u ON b.user_id = u.id 
              LEFT JOIN services s ON b.service_id = s.id 
              ORDER BY b.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    $history = array();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)){
        array_push($history, $row);
    }
    echo json_encode($history);

} else {
    http_response_code(400);
    echo json_encode(array("message" => "Invalid action."));
}
?>
