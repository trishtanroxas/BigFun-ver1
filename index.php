<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Controllers\AuthController;

$route = $_GET['route'] ?? 'home';

switch ($route) {
    case 'login':
        $controller = new AuthController();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->login();
        } else {
            $controller->showLogin();
        }
        break;
    
    default:
        // For now, redirect to the old index.php or handle home
        echo "<h1>Welcome to BigFun MVC</h1><p><a href='index.php?route=login'>Login</a></p>";
        break;
}