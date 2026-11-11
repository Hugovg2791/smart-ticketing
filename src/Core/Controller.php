<?php
namespace App\Core;

class Controller {
    protected function view($view, $data = []) {
        extract($data);
        $viewFile = __DIR__ . '/../Views/' . $view . '.php';
        $layoutFile = __DIR__ . '/../Views/layouts/main.php';

        if (!file_exists($viewFile)) {
            die("View does not exist.");
        }

        
        // Capture view content via output buffering
        ob_start();
        require $viewFile;
        $viewContent = ob_get_clean();

        // Render inside layout if it exists
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo $viewContent;
        }
    }

    protected function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    protected function abort(int $code): void {
        http_response_code($code);
        require __DIR__ . '/../Views/errors/' . $code . '.php';
        exit;
    }
}

