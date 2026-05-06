<?php

namespace App\Core;

class Controller {
    protected function view(string $name, array $data = []): void {
        extract($data);
        $viewPath = __DIR__ . "/../../resources/views/{$name}.php";
        
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            die("View {$name} not found.");
        }
    }

    protected function json(array $data): void {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
