<?php
namespace App\Models;

use App\Core\Database;

/**
 * Modelo para gestionar las sugerencias generadas por la IA (Gemini)
 * en la tabla ai_suggestions.
 */
class AiSuggestionModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Crea una nueva sugerencia de IA vinculada a un mensaje.
     * Devuelve el ID de la sugerencia creada.
     */
    public function create(int $messageId, ?string $category, ?string $sentiment, string $suggestedResponse): int {
        $stmt = $this->db->prepare(
            "INSERT INTO ai_suggestions (message_id, suggested_category, suggested_sentiment, suggested_response)
             VALUES (:message_id, :suggested_category, :suggested_sentiment, :suggested_response)"
        );
        $stmt->execute([
            ':message_id'           => $messageId,
            ':suggested_category'   => $category,
            ':suggested_sentiment'  => $sentiment,
            ':suggested_response'   => $suggestedResponse
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Busca la sugerencia de IA asociada a un mensaje.
     */
    public function findByMessageId(int $messageId): ?array {
        $stmt = $this->db->prepare(
            "SELECT * FROM ai_suggestions WHERE message_id = :message_id"
        );
        $stmt->execute([':message_id' => $messageId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function deleteByMessageId(int $messageId): void {
        $stmt = $this->db->prepare("DELETE FROM ai_suggestions WHERE message_id = :message_id");
        $stmt->execute([':message_id' => $messageId]);
    }

    public function deleteByTicketId(int $ticketId): void {
        $stmt = $this->db->prepare(
            "DELETE FROM ai_suggestions WHERE message_id IN (SELECT id FROM messages WHERE ticket_id = :ticket_id)"
        );
        $stmt->execute([':ticket_id' => $ticketId]);
    }
}
