<?php
require_once 'cors.php';
require_once 'src/Config/Database.php';
require_once 'src/Controllers/BookingController.php';

use Api\Config\Database;
use Api\Controllers\BookingController;

$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? $_GET['id'] : null;
$method = $_SERVER['REQUEST_METHOD'];

$controller = new BookingController($db, $method, $id);
$controller->processRequest();
?>
