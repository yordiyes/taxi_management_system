<?php
require_once 'cors.php';
require_once 'src/Config/Database.php';
require_once 'src/Controllers/DriverController.php';

use Api\Config\Database;
use Api\Controllers\DriverController;

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? $_GET['id'] : null;
$method = $_SERVER['REQUEST_METHOD'];

$controller = new DriverController($db, $method, $id);
$controller->processRequest();
?>
