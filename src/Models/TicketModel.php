<?php
namespace App\Models;

use App\Core\Database;

/**
 * Modelo para gestionar los tickets (hilos de incidencias) en la base de datos.
 * Utiliza sentencias preparadas PDO para prevenir inyección SQL.
 */
class TicketModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Crea un nuevo ticket en la base de datos.
     * Devuelve el ID del ticket creado.
     */
    public function create(string $subject, string $status = 'No leído', ?string $gmailThreadId = null): int {
        $stmt = $this->db->prepare(
            "INSERT INTO tickets (subject, status, gmail_thread_id) VALUES (:subject, :status, :gmail_thread_id)"
        );
        $stmt->execute([
            ':subject'         => $subject,
            ':status'          => $status,
            ':gmail_thread_id' => $gmailThreadId
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Busca un ticket por su ID.
     */
    public function findById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM tickets WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Busca un ticket por su gmail_thread_id.
     */
    public function findByGmailThreadId(string $threadId): ?array {
        $stmt = $this->db->prepare("SELECT * FROM tickets WHERE gmail_thread_id = :thread_id");
        $stmt->execute([':thread_id' => $threadId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Actualiza el estado del ticket.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE tickets SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }

    /**
     * Actualiza el timestamp del ticket para reflejar actividad reciente.
     */
    public function updateTimestamp(int $id): bool {
        $stmt = $this->db->prepare(
            "UPDATE tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = :id"
        );
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Obtiene todos los tickets ordenados por última actualización.
     */
    public function findAll(): array {
        $stmt = $this->db->query("SELECT * FROM tickets ORDER BY updated_at DESC");
        return $stmt->fetchAll();
    }

    /**
     * Obtiene tickets filtrados con datos de IA incluidos mediante JOINs.
     * Construye dinámicamente la cláusula WHERE según los filtros recibidos.
     * Usa sentencias preparadas PDO para prevenir inyección SQL.
     */
    public function findAllFiltered(array $filters = [], array $sort = []): array {
        $where = [];
        $params = [];

        // Filtro por estado
        if (!empty($filters['status'])) {
            $where[] = 't.status = :status';
            $params[':status'] = $filters['status'];
        }

        // Filtro por categoría IA
        if (!empty($filters['category'])) {
            $where[] = 'ais.suggested_category = :category';
            $params[':category'] = $filters['category'];
        }

        // Filtro por sentimiento IA
        if (!empty($filters['sentiment'])) {
            $where[] = 'ais.suggested_sentiment = :sentiment';
            $params[':sentiment'] = $filters['sentiment'];
        }

        // Búsqueda de texto libre por asunto o remitente
        if (!empty($filters['search'])) {
            $where[] = '(t.subject LIKE :search_subject OR m_search.sender_email LIKE :search_email)';
            $params[':search_subject'] = '%' . $filters['search'] . '%';
            $params[':search_email']   = '%' . $filters['search'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sortable = [
            'id'            => 't.id',
            'subject'       => 't.subject',
            'status'        => 't.status',
            'ai_category'   => 'ai_category',
            'ai_sentiment'  => 'ai_sentiment',
            'message_count' => 'message_count',
            'updated_at'    => 't.updated_at',
        ];
        $sortCol = $sortable[$sort['col'] ?? ''] ?? 't.updated_at';
        $sortDir = strtoupper($sort['dir'] ?? '') === 'ASC' ? 'ASC' : 'DESC';

        $sql = "
            SELECT
                t.id, t.gmail_thread_id, t.subject, t.status, t.category,
                t.sentiment, t.assigned_to, t.created_at, t.updated_at,
                t.has_new_message,
                MAX(ais.suggested_category) AS ai_category,
                MAX(ais.suggested_sentiment) AS ai_sentiment,
                (SELECT COUNT(*) FROM messages WHERE ticket_id = t.id) AS message_count
            FROM tickets t
            LEFT JOIN (
                SELECT m1.ticket_id, m1.id AS first_message_id
                FROM messages m1
                INNER JOIN (
                    SELECT ticket_id, MIN(id) AS min_id
                    FROM messages
                    GROUP BY ticket_id
                ) m2 ON m1.id = m2.min_id
            ) fm ON fm.ticket_id = t.id
            LEFT JOIN ai_suggestions ais ON ais.message_id = fm.first_message_id
            LEFT JOIN messages m_search ON m_search.ticket_id = t.id
            {$whereClause}
            GROUP BY t.id, t.gmail_thread_id, t.subject, t.status, t.category,
                     t.sentiment, t.assigned_to, t.created_at, t.updated_at, t.has_new_message
            ORDER BY {$sortCol} {$sortDir}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function markNewMessage(int $id): void {
        $stmt = $this->db->prepare("UPDATE tickets SET has_new_message = 1 WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function clearNewMessage(int $id): void {
        $stmt = $this->db->prepare("UPDATE tickets SET has_new_message = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM tickets WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Obtiene las categorías IA distintas existentes en la base de datos.
     */
    public function getDistinctCategories(): array {
        $stmt = $this->db->query(
            "SELECT DISTINCT suggested_category FROM ai_suggestions WHERE suggested_category IS NOT NULL AND suggested_category != '' ORDER BY suggested_category"
        );
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Obtiene los sentimientos IA distintos existentes en la base de datos.
     */
    public function getDistinctSentiments(): array {
        $stmt = $this->db->query(
            "SELECT DISTINCT suggested_sentiment FROM ai_suggestions WHERE suggested_sentiment IS NOT NULL AND suggested_sentiment != '' ORDER BY suggested_sentiment"
        );
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }
}
