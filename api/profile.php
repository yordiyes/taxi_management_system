<?php
require_once 'cors.php';
require_once 'src/Config/Database.php';
require_once 'src/Controllers/ProfileController.php';

use Api\Config\Database;
use Api\Controllers\ProfileController;

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

$controller = new ProfileController($db, $method);
$controller->processRequest();
?>
