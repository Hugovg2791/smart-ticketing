<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\GmailService;

class SettingsController extends Controller {

    public function index() {
        $isConnected = false;
        try {
            $gmail = new GmailService();
            $client = $gmail->getClient();
            $isConnected = $client->getAccessToken() !== null && !$client->isAccessTokenExpired();
        } catch (\Exception $e) {
            // GmailService throws if credentials are missing; treat as not connected
        }

        $flash = $this->consumeFlash();

        $this->view('settings/index', [
            'pageTitle'   => 'Configuración',
            'isConnected' => $isConnected,
            'flash'       => $flash,
        ]);
    }

    public function connect() {
        $gmail = new GmailService();
        $client = $gmail->getClient();

        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        $client->setState($state);
        $authUrl = $client->createAuthUrl();

        header('Location: ' . $authUrl);
        exit;
    }

    public function callback() {
        if (isset($_GET['error'])) {
            $this->setFlash('error', 'Google rechazó la autorización: ' . htmlspecialchars($_GET['error']));
            header('Location: /settings');
            exit;
        }

        $state = $_GET['state'] ?? '';
        if (empty($state) || $state !== ($_SESSION['oauth_state'] ?? '')) {
            $this->setFlash('error', 'Error de seguridad: token de estado inválido.');
            header('Location: /settings');
            exit;
        }
        unset($_SESSION['oauth_state']);

        $code = $_GET['code'] ?? '';
        if (empty($code)) {
            $this->setFlash('error', 'No se recibió el código de autorización de Google.');
            header('Location: /settings');
            exit;
        }

        try {
            $gmail = new GmailService();
            $client = $gmail->getClient();
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                $this->setFlash('error', 'Error al obtener el token: ' . $token['error_description']);
                header('Location: /settings');
                exit;
            }

            $client->setAccessToken($token);
            $gmail->saveToken($token);

            $this->setFlash('success', 'Gmail conectado correctamente.');
        } catch (\Exception $e) {
            $this->setFlash('error', 'Error inesperado: ' . $e->getMessage());
        }

        header('Location: /settings');
        exit;
    }

    private function setFlash(string $type, string $message): void {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    private function consumeFlash(): ?array {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
}
