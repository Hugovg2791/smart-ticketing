<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\GmailService;
use App\Models\TicketModel;
use App\Models\MessageModel;
use App\Models\AiSuggestionModel;

/**
 * Controlador del dashboard de tickets.
 * Gestiona las vistas de listado y detalle de tickets.
 */
class TicketController extends Controller
{
    private TicketModel $ticketModel;
    private MessageModel $messageModel;
    private AiSuggestionModel $aiSuggestionModel;

    public function __construct()
    {
        $this->ticketModel = new TicketModel();
        $this->messageModel = new MessageModel();
        $this->aiSuggestionModel = new AiSuggestionModel();
    }

    /**
     * Listado de todos los tickets con filtros y búsqueda.
     * GET /tickets
     */
    public function index()
    {
        // Leer y sanitizar filtros de la query string
        $filters = [
            'status'    => trim($_GET['status'] ?? ''),
            'category'  => trim($_GET['category'] ?? ''),
            'sentiment' => trim($_GET['sentiment'] ?? ''),
            'search'    => trim($_GET['search'] ?? ''),
            'sort'      => trim($_GET['sort'] ?? ''),
            'dir'       => trim($_GET['dir'] ?? ''),
        ];

        $sort = ['col' => $filters['sort'], 'dir' => $filters['dir']];

        // Obtener tickets filtrados con datos de IA incluidos
        $tickets = $this->ticketModel->findAllFiltered($filters, $sort);

        // Obtener valores para los desplegables dinámicos
        $categories = $this->ticketModel->getDistinctCategories();
        $sentiments = $this->ticketModel->getDistinctSentiments();

        $this->view('tickets/index', [
            'pageTitle'  => 'Tickets',
            'tickets'    => $tickets,
            'filters'    => $filters,
            'categories' => $categories,
            'sentiments' => $sentiments,
        ]);
    }

    /**
     * Detalle de un ticket: hilo de mensajes + panel IA.
     * GET /tickets/{id}
     */
    public function show(string $id)
    {
        $ticket = $this->ticketModel->findById((int) $id);

        if (!$ticket) {
            http_response_code(404);
            $this->view('tickets/not_found', [
                'pageTitle' => 'Ticket no encontrado'
            ]);
            return;
        }

        // Si el ticket está 'No leído' (recién llegado), al abrirlo por un agente pasa a 'En Proceso'
        if ($ticket['status'] === 'No leído') {
            $this->ticketModel->updateStatus((int) $id, 'En Proceso');
            $ticket['status'] = 'En Proceso';
        }

        // Limpiar el indicador de mensajes nuevos al abrir el ticket
        $this->ticketModel->clearNewMessage((int) $id);

        $messages = $this->messageModel->findByTicketId((int) $id);

        // Cargar la sugerencia IA para cada mensaje
        foreach ($messages as &$message) {
            $message['ai_suggestion'] = $this->aiSuggestionModel->findByMessageId($message['id']);
        }

        // Sugerencia IA del mensaje más reciente para pre-rellenar el formulario
        $latestSuggestion = null;
        foreach (array_reverse($messages) as $msg) {
            if (!empty($msg['ai_suggestion'])) {
                $latestSuggestion = $msg['ai_suggestion'];
                break;
            }
        }

        // Consumir mensajes flash de la sesión
        $flashSuccess = $_SESSION['flash_success'] ?? null;
        $flashError   = $_SESSION['flash_error']   ?? null;
        unset($_SESSION['flash_success'], $_SESSION['flash_error']);

        $this->view('tickets/show', [
            'pageTitle'       => 'Ticket #' . $ticket['id'],
            'ticket'          => $ticket,
            'messages'        => $messages,
            'latestSuggestion' => $latestSuggestion,
            'flashSuccess'    => $flashSuccess,
            'flashError'      => $flashError,
        ]);
    }

    /**
     * Procesa el formulario de respuesta y envía el correo al cliente.
     * POST /tickets/{id}/reply
     */
    public function sendReply(string $id): void
    {
        $ticketId  = (int) $id;
        $replyBody = trim($_POST['reply_body'] ?? '');

        if ($replyBody === '') {
            $_SESSION['flash_error'] = 'La respuesta no puede estar vacía.';
            header('Location: /tickets/' . $ticketId);
            exit;
        }

        $ticket = $this->ticketModel->findById($ticketId);
        if (!$ticket) {
            http_response_code(404);
            return;
        }

        // Obtener el mensaje entrante más reciente para el hilo
        $messages = $this->messageModel->findByTicketId($ticketId);
        $latestIncoming = null;
        foreach (array_reverse($messages) as $msg) {
            if (($msg['direction'] ?? 'incoming') === 'incoming') {
                $latestIncoming = $msg;
                break;
            }
        }

        if (!$latestIncoming) {
            $_SESSION['flash_error'] = 'No se encontró un mensaje original al que responder.';
            header('Location: /tickets/' . $ticketId);
            exit;
        }

        $recipientEmail = $latestIncoming['sender_email'];
        $subject        = 'Re: ' . $ticket['subject'];
        $inReplyTo      = $latestIncoming['message_id'];

        // Enviar correo via Gmail API (incluyendo threadId para mantener el hilo)
        $gmailService = new GmailService();
        $gmailThreadId = $ticket['gmail_thread_id'] ?? '';
        $htmlBody = $this->buildReplyHtml($replyBody);
        $sentGmailId  = $gmailService->sendEmail($recipientEmail, $subject, $htmlBody, $inReplyTo, $gmailThreadId);

        if ($sentGmailId === false) {
            $_SESSION['flash_error'] = 'Error al enviar el correo. Por favor, inténtalo de nuevo.';
            header('Location: /tickets/' . $ticketId);
            exit;
        }

        // Persistir el mensaje saliente y actualizar estado en una transacción
        $db = Database::getInstance()->getConnection();
        try {
            $db->beginTransaction();

            $agentId = (int) $_SESSION['user_id'];
            $stmt = $db->prepare("SELECT email FROM users WHERE id = :id");
            $stmt->execute([':id' => $agentId]);
            $agentEmail = $stmt->fetchColumn() ?: '';

            $outgoingMessageId = '<' . uniqid('reply.', true) . '@local>';

            $stmt = $db->prepare(
                "INSERT INTO messages (ticket_id, sender_id, sender_email, body_text, message_id, in_reply_to, direction)
                 VALUES (:ticket_id, :sender_id, :sender_email, :body_text, :message_id, :in_reply_to, 'outgoing')"
            );
            $stmt->execute([
                ':ticket_id'    => $ticketId,
                ':sender_id'    => $agentId,
                ':sender_email' => $agentEmail,
                ':body_text'    => $replyBody,
                ':message_id'   => $outgoingMessageId,
                ':in_reply_to'  => $inReplyTo,
            ]);

            $stmt = $db->prepare(
                "UPDATE tickets SET status = 'En Proceso', updated_at = CURRENT_TIMESTAMP WHERE id = :id"
            );
            $stmt->execute([':id' => $ticketId]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = 'El correo fue enviado pero hubo un error al registrar la respuesta.';
            header('Location: /tickets/' . $ticketId);
            exit;
        }

        $_SESSION['flash_success'] = 'Respuesta enviada correctamente.';
        header('Location: /tickets/' . $ticketId);
        exit;
    }

    /**
     * Elimina un ticket completo (AI suggestions → mensajes → ticket).
     * POST /tickets/{id}/delete
     */
    public function deleteTicket(string $id): void
    {
        $ticketId = (int) $id;

        $ticket = $this->ticketModel->findById($ticketId);
        if (!$ticket) {
            $this->abort(404);
        }

        $this->aiSuggestionModel->deleteByTicketId($ticketId);
        $this->messageModel->deleteByTicketId($ticketId);
        $this->ticketModel->delete($ticketId);

        $_SESSION['flash_success'] = 'Ticket eliminado correctamente.';
        header('Location: /tickets');
        exit;
    }

    /**
     * Elimina un mensaje individual de un ticket (AI suggestion → mensaje).
     * POST /tickets/{id}/messages/{messageId}/delete
     */
    public function deleteMessage(string $id, string $messageId): void
    {
        $ticketId  = (int) $id;
        $msgId     = (int) $messageId;

        $this->aiSuggestionModel->deleteByMessageId($msgId);
        $this->messageModel->delete($msgId);

        $_SESSION['flash_success'] = 'Mensaje eliminado correctamente.';
        header('Location: /tickets/' . $ticketId);
        exit;
    }

    /**
     * Cambia el estado de un ticket.
     * POST /tickets/{id}/status
     */
    public function updateStatus(string $id): void
    {
        $ticketId = (int) $id;
        $status = trim($_POST['status'] ?? '');

        $allowedStatuses = ['No leído', 'En Proceso', 'Resuelto'];

        if (!in_array($status, $allowedStatuses, true)) {
            $_SESSION['flash_error'] = 'Estado no válido.';
            header('Location: /tickets/' . $ticketId);
            exit;
        }

        $ticket = $this->ticketModel->findById($ticketId);
        if (!$ticket) {
            http_response_code(404);
            return;
        }

        $this->ticketModel->updateStatus($ticketId, $status);

        $_SESSION['flash_success'] = 'Estado actualizado correctamente.';
        header('Location: /tickets/' . $ticketId);
        exit;
    }

    private function buildReplyHtml(string $plainText): string
    {
        $body = nl2br(htmlspecialchars($plainText, ENT_QUOTES, 'UTF-8'));

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:24px;">
    <tr><td>
      <table width="100%" cellpadding="0" cellspacing="0" style="width:100%;">

        <!-- Cabecera -->
        <tr>
          <td style="background:#212529;padding:20px 28px;border-radius:6px 6px 0 0;">
            <span style="color:#ffffff;font-size:18px;font-weight:bold;">
              &#9889; Electrotech
            </span>
          </td>
        </tr>

        <!-- Cuerpo -->
        <tr>
          <td style="background:#ffffff;padding:28px;font-size:15px;line-height:1.7;color:#212529;border-left:1px solid #dee2e6;border-right:1px solid #dee2e6;">
            {$body}
          </td>
        </tr>

        <!-- Pie de firma -->
        <tr>
          <td style="background:#ffffff;padding:16px 28px 24px;border-top:1px solid #dee2e6;border-left:1px solid #dee2e6;border-right:1px solid #dee2e6;border-radius:0 0 6px 6px;">
            <p style="margin:0;font-size:13px;color:#6c757d;">
              <strong>Equipo de Soporte Técnico</strong><br>
              Electrotech
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
