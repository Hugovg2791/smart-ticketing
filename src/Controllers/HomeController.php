<?php
namespace App\Controllers;

use App\Core\Controller;

class HomeController extends Controller {
    public function index() {
        if (!empty($_SESSION['user_id'])) {
            header('Location: /tickets');
        } else {
            header('Location: /login');
        }
        exit;
    }


}
