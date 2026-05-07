<?php

namespace App\Controllers;

use App\Core\Controller;

class AdminController extends Controller {
    private function checkAuth(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['admin_id'])) {
            header("Location: index.php?route=admin-login");
            exit();
        }
    }

    public function dashboard(): void {
        $this->checkAuth();
        $this->view('admin/admin-dashboard');
    }

    public function login(): void {
        $this->view('admin/admin-login');
    }

    public function appointments(): void {
        $this->checkAuth();
        $this->view('admin/admin-appointments');
    }

    public function customers(): void {
        $this->checkAuth();
        $this->view('admin/admin-customers');
    }

    public function services(): void {
        $this->checkAuth();
        $this->view('admin/admin-services');
    }

    public function bookingApproval(): void {
        $this->checkAuth();
        $this->view('admin/admin-booking-approval');
    }

    public function manage(): void {
        $this->checkAuth();
        $this->view('admin/admin-manage');
    }
}
