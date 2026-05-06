<?php

namespace App\Controllers;

use App\Core\Controller;

class CustomerController extends Controller {
    public function dashboard(): void {
        $this->view('customer/book-dashboard');
    }

    public function profile(): void {
        $this->view('customer/profile-manage');
    }

    public function cart(): void {
        $this->view('customer/cart');
    }

    public function checkout(): void {
        $this->view('customer/checkout');
    }

    public function invoices(): void {
        $this->view('customer/invoices');
    }

    public function bookingStatus(): void {
        $this->view('customer/booking-status');
    }

    public function products(): void {
        $this->view('customer/products');
    }

    public function notifications(): void {
        $this->view('customer/notification');
    }
}
