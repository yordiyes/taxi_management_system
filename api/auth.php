<?php
require_once 'cors.php';
require_once 'src/Config/Database.php';
require_once 'src/Controllers/AuthController.php';

use Api\Config\Database;
use Api\Controllers\AuthController;

$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : null;

if (!$action) {
    http_response_code(400);
    echo json_encode(array("message" => "Action parameter is missing."));
    exit();
}

$controller = new AuthController($db, $action);
$controller->processRequest();
?>
