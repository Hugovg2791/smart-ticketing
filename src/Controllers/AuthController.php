<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use PDO;

class AuthController extends Controller {

    /**
     * Muestra el formulario de login. Redirige si ya está logueado.
     */
    public function showLogin() {
        if (isset($_SESSION['user_id'])) {
            header('Location: ' . $this->postLoginRedirect());
            exit;
        }

        $error = '';
        if (isset($_SESSION['login_error'])) {
            $error = $_SESSION['login_error'];
            unset($_SESSION['login_error']);
        }

        $this->view('auth/login', [
            'pageTitle' => 'Iniciar Sesión',
            'error' => $error
        ]);
    }

    /**
     * Procesa la solicitud POST del formulario de login
     */
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /login');
            exit;
        }

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $_SESSION['login_error'] = 'Por favor, introduce correo y contraseña.';
            header('Location: /login');
            exit;
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            header('Location: ' . $this->postLoginRedirect());
            exit;
        } else {
            $_SESSION['login_error'] = 'Credenciales inválidas. Por favor, inténtalo de nuevo.';
            header('Location: /login');
            exit;
        }
    }

    private function postLoginRedirect(): string {
        return '/tickets';
    }

    /**
     * Cierra la sesión
     */
    public function logout() {
        // Unset all session variables
        $_SESSION = [];

        // If it's desired to kill the session, also delete the session cookie.
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // Finally, destroy the session.
        session_destroy();
        
        header('Location: /login');
        exit;
    }
}
