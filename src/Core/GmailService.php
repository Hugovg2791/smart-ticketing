<?php
namespace App\Core;

use Google\Client;
use Google\Service\Gmail;

/**
 * Servicio encargado de la autenticación OAuth 2.0 con la API de Gmail
 * y de la obtención y procesamiento de correos no leídos.
 */
class GmailService
{
    private Client $client;
    private string $tokenPath;

    public function __construct()
    {
        $this->tokenPath = __DIR__ . '/../../storage/gmail-token.json';

        $this->client = new Client();
        $this->client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
        $this->client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
        $this->client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);
        $this->client->addScope(Gmail::GMAIL_MODIFY);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');

        // Cargar token almacenado si existe
        if (file_exists($this->tokenPath)) {
            $token = json_decode(file_get_contents($this->tokenPath), true);
            $this->client->setAccessToken($token);

            // Refrescar el token si ha expirado
            if ($this->client->isAccessTokenExpired() && $this->client->getRefreshToken()) {
                $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                $this->saveToken($this->client->getAccessToken());
            }
        }
    }

    /**
     * Devuelve el cliente de Google configurado.
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * Guarda el token de acceso en el sistema de archivos.
     */
    public function saveToken(array $token): void
    {
        if (!is_dir(dirname($this->tokenPath))) {
            mkdir(dirname($this->tokenPath), 0700, true);
        }
        file_put_contents($this->tokenPath, json_encode($token));
    }

    /**
     * Obtiene los correos no leídos de la bandeja de entrada de Gmail.
     * Devuelve un array de correos procesados con metadatos y cuerpo limpio.
     */
    public function getUnreadMessages(): array
    {
        $service = new Gmail($this->client);
        $results = $service->users_messages->listUsersMessages('me', [
            'q' => 'is:unread',
            'maxResults' => 20
        ]);

        $emails = [];
        foreach ($results->getMessages() as $messageRef) {
            $message = $service->users_messages->get('me', $messageRef->getId(), ['format' => 'full']);
            $headers = $this->extractHeaders($message->getPayload()->getHeaders());
            $body = $this->extractBody($message->getPayload());

            $emails[] = [
                'gmail_id' => $message->getId(),
                'thread_id' => $message->getThreadId(),
                'message_id' => $headers['Message-ID'] ?? '',
                'in_reply_to' => $headers['In-Reply-To'] ?? '',
                'from' => $headers['From'] ?? '',
                'subject' => $headers['Subject'] ?? '(Sin asunto)',
                'date' => $headers['Date'] ?? '',
                'body_text' => $this->cleanBody($body),
            ];

            // Marcar como leído después de procesar
            $service->users_messages->modify('me', $message->getId(), new \Google\Service\Gmail\ModifyMessageRequest([
                'removeLabelIds' => ['UNREAD']
            ]));
        }

        return $emails;
    }

    /**
     * Extrae las cabeceras relevantes del correo.
     */
    private function extractHeaders(array $headers): array
    {
        $result = [];
        $relevantHeaders = ['From', 'Subject', 'Date', 'Message-ID', 'In-Reply-To'];
        foreach ($headers as $header) {
            if (in_array($header->getName(), $relevantHeaders)) {
                $result[$header->getName()] = $header->getValue();
            }
        }
        return $result;
    }

    /**
     * Extrae el cuerpo del mensaje desde el payload de Gmail.
     */
    private function extractBody($payload): string
    {
        // Intentar obtener texto plano directamente
        if ($payload->getMimeType() === 'text/plain' && $payload->getBody()->getData()) {
            return base64_decode(strtr($payload->getBody()->getData(), '-_', '+/'));
        }

        // Buscar en las partes del mensaje
        $parts = $payload->getParts();
        if ($parts) {
            foreach ($parts as $part) {
                if ($part->getMimeType() === 'text/plain' && $part->getBody()->getData()) {
                    return base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
                }
            }
            // Si no hay texto plano, buscar HTML y convertir
            foreach ($parts as $part) {
                if ($part->getMimeType() === 'text/html' && $part->getBody()->getData()) {
                    $html = base64_decode(strtr($part->getBody()->getData(), '-_', '+/'));
                    return strip_tags($html);
                }
            }
        }

        return '';
    }

    /**
     * Envía un correo electrónico mediante la API de Gmail preservando el hilo.
     * Devuelve el Gmail message ID del mensaje enviado, o false en caso de error.
     */
    public function sendEmail(string $to, string $subject, string $body, string $inReplyTo = '', string $threadId = ''): string|false
    {
        // Refrescar token si ha expirado
        if ($this->client->isAccessTokenExpired() && $this->client->getRefreshToken()) {
            $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
            $this->saveToken($this->client->getAccessToken());
        }

        $service = new Gmail($this->client);

        $profile = $service->users->getProfile('me');
        $fromEmail = $profile->getEmailAddress();

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=utf-8',
            'From: ' . $fromEmail,
            'To: ' . $to,
            'Subject: ' . $subject,
        ];

        if ($inReplyTo !== '') {
            $headers[] = 'In-Reply-To: ' . $inReplyTo;
            $headers[] = 'References: ' . $inReplyTo;
        }

        $rawMessage = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $encoded = rtrim(strtr(base64_encode($rawMessage), '+/', '-_'), '=');

        $gmailMessage = new \Google\Service\Gmail\Message();
        $gmailMessage->setRaw($encoded);

        // Mantener el mensaje en el mismo hilo de Gmail
        if ($threadId !== '') {
            $gmailMessage->setThreadId($threadId);
        }

        try {
            $sent = $service->users_messages->send('me', $gmailMessage);
            return $sent->getId();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Limpia el cuerpo del mensaje: elimina firmas y líneas en blanco excesivas.
     */
    private function cleanBody(string $body): string
    {
        // Eliminar firmas comunes (líneas que empiezan con --)
        $body = preg_replace('/--\s*\n.*/s', '', $body);
        // Eliminar líneas "On ... wrote:" típicas de respuestas
        $body = preg_replace('/\nOn .+ wrote:\s*\n.*/s', '', $body);
        // Eliminar líneas en blanco excesivas
        $body = preg_replace('/\n{3,}/', "\n\n", $body);
        return trim($body);
    }
}
