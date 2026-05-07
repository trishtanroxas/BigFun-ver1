<?php

namespace App\Controllers;

use App\Core\Controller;

class CustomerController extends Controller {
    private function checkAuth(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            header("Location: index.php?route=login-form");
            exit();
        }
    }

    public function dashboard(): void {
        $this->checkAuth();
        $this->view('customer/book-dashboard');
    }

    public function profile(): void {
        $this->checkAuth();
        $this->view('customer/profile-manage');
    }

    public function cart(): void {
        $this->checkAuth();
        $this->view('customer/cart');
    }

    public function checkout(): void {
        $this->checkAuth();
        $this->view('customer/checkout');
    }

    public function invoices(): void {
        $this->checkAuth();
        $this->view('customer/invoices');
    }

    public function bookingStatus(): void {
        $this->checkAuth();
        $this->view('customer/booking-status');
    }

    public function products(): void {
        $this->checkAuth();
        $this->view('customer/products');
    }

    public function notifications(): void {
        $this->checkAuth();
        $this->view('customer/notification');
    }
}
