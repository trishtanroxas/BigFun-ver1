<?php

namespace App\Controllers;

use App\Core\Controller;

class AdminController extends Controller {
    public function dashboard(): void {
        $this->view('admin/admin-dashboard');
    }

    public function login(): void {
        $this->view('admin/admin-login');
    }

    public function appointments(): void {
        $this->view('admin/admin-appointments');
    }

    public function customers(): void {
        $this->view('admin/admin-customers');
    }

    public function services(): void {
        $this->view('admin/admin-services');
    }

    public function bookingApproval(): void {
        $this->view('admin/admin-booking-approval');
    }

    public function manage(): void {
        $this->view('admin/admin-manage');
    }
}
