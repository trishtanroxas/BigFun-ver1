<?php

namespace App\Controllers;

use App\Core\Controller;

class PageController extends Controller {
    public function home(): void {
        $this->view('general/home');
    }

    public function about(): void {
        $this->view('general/about');
    }

    public function contact(): void {
        $this->view('general/contact');
    }

    public function services(): void {
        $this->view('general/services');
    }
}
