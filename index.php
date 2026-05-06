<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\CustomerController;
use App\Controllers\PageController;

$route = $_GET['route'] ?? 'home';

// Basic Router
switch ($route) {
    // --- AUTH ROUTES ---
    case 'login':
        (new AuthController())->login();
        break;
    case 'login-form':
        (new AuthController())->showLogin();
        break;
    case 'signup':
        (new AuthController())->signup();
        break;
    case 'forgot-password':
        (new AuthController())->forgotPassword();
        break;
    case 'reset-password':
        (new AuthController())->resetPassword();
        break;

    // --- ADMIN ROUTES ---
    case 'admin-dashboard':
        (new AdminController())->dashboard();
        break;
    case 'admin-login':
        (new AdminController())->login();
        break;
    case 'admin-appointments':
        (new AdminController())->appointments();
        break;
    case 'admin-customers':
        (new AdminController())->customers();
        break;
    case 'admin-services':
        (new AdminController())->services();
        break;
    case 'admin-booking-approval':
        (new AdminController())->bookingApproval();
        break;
    case 'admin-manage':
        (new AdminController())->manage();
        break;

    // --- CUSTOMER ROUTES ---
    case 'dashboard':
        (new CustomerController())->dashboard();
        break;
    case 'profile':
        (new CustomerController())->profile();
        break;
    case 'cart':
        (new CustomerController())->cart();
        break;
    case 'checkout':
        (new CustomerController())->checkout();
        break;
    case 'invoices':
        (new CustomerController())->invoices();
        break;
    case 'booking-status':
        (new CustomerController())->bookingStatus();
        break;
    case 'products':
        (new CustomerController())->products();
        break;
    case 'notifications':
        (new CustomerController())->notifications();
        break;

    // --- GENERAL ROUTES ---
    case 'about':
        (new PageController())->about();
        break;
    case 'contact':
        (new PageController())->contact();
        break;
    case 'services':
        (new PageController())->services();
        break;
    
    // --- DEFAULT ---
    case 'home':
    default:
        (new PageController())->home();
        break;
}