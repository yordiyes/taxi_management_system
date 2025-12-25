<?php
require_once 'cors.php';
require_once 'src/Config/Database.php';
require_once 'src/Controllers/StatsController.php';

use Api\Config\Database;
use Api\Controllers\StatsController;

$database = new Database();
$db = $database->getConnection();

$action = isset($_GET['action']) ? $_GET['action'] : '';

$controller = new StatsController($db, $action);
$controller->processRequest();
?>
