<?php
namespace App\Models;

use App\Core\Database;

/**
 * Modelo para gestionar los mensajes individuales (correos) dentro de un ticket.
 * Incluye lógica de detección de hilos mediante la cabecera In-Reply-To.
 */
class MessageModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Crea un nuevo mensaje vinculado a un ticket.
     * Devuelve el ID del mensaje creado.
     */
    public function create(int $ticketId, string $senderEmail, string $bodyText, string $messageId, ?string $inReplyTo = null): int {
        $stmt = $this->db->prepare(
            "INSERT INTO messages (ticket_id, sender_email, body_text, message_id, in_reply_to)
             VALUES (:ticket_id, :sender_email, :body_text, :message_id, :in_reply_to)"
        );
        $stmt->execute([
            ':ticket_id'    => $ticketId,
            ':sender_email' => $senderEmail,
            ':body_text'    => $bodyText,
            ':message_id'   => $messageId,
            ':in_reply_to'  => $inReplyTo
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Busca un mensaje por su cabecera Message-ID.
     * Se usa para detectar hilos de conversación.
     */
    public function findByMessageId(string $messageId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM messages WHERE message_id = :message_id");
        $stmt->execute([':message_id' => $messageId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene todos los mensajes de un ticket ordenados cronológicamente.
     */
    public function findByTicketId(int $ticketId): array {
        $stmt = $this->db->prepare(
            "SELECT * FROM messages WHERE ticket_id = :ticket_id ORDER BY created_at ASC"
        );
        $stmt->execute([':ticket_id' => $ticketId]);
        return $stmt->fetchAll();
    }

    /**
     * Detecta si un correo pertenece a un hilo existente buscando
     * la cabecera In-Reply-To entre los Message-ID almacenados.
     * Devuelve el ticket_id si se encuentra, o null si es un correo nuevo.
     */
    public function deleteByTicketId(int $ticketId): void {
        $stmt = $this->db->prepare("DELETE FROM messages WHERE ticket_id = :ticket_id");
        $stmt->execute([':ticket_id' => $ticketId]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM messages WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function findTicketByInReplyTo(string $inReplyTo): ?int {
        $stmt = $this->db->prepare(
            "SELECT ticket_id FROM messages WHERE message_id = :in_reply_to LIMIT 1"
        );
        $stmt->execute([':in_reply_to' => $inReplyTo]);
        $result = $stmt->fetch();
        return $result ? (int) $result['ticket_id'] : null;
    }
}
