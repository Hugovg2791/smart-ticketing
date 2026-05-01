<?php
/**
 * Vista: Detalle de Ticket
 * Variables: $ticket, $messages, $latestSuggestion, $flashSuccess, $flashError
 */
?>

<!-- Flash messages -->
<?php if (!empty($flashSuccess)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if (!empty($flashError)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="/tickets">Tickets</a></li>
        <li class="breadcrumb-item active">Ticket #<?= $ticket['id'] ?></li>
    </ol>
</nav>

<!-- Ticket Header -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="h4 mb-1"><?= htmlspecialchars($ticket['subject']) ?></h1>
                <small class="text-muted">
                    Creado: <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                    · Actualizado: <?= date('d/m/Y H:i', strtotime($ticket['updated_at'])) ?>
                </small>
            </div>
            <div>
                <?php
                $statusClass = match($ticket['status']) {
                    'No leído'             => 'secondary',
                    'En Proceso'           => 'warning',
                    'Resuelto'             => 'success',
                    default                => 'secondary'
                };
                ?>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-<?= $statusClass ?> fs-6">
                        <?= htmlspecialchars($ticket['status']) ?>
                    </span>

                    <form method="POST" action="/tickets/<?= (int) $ticket['id'] ?>/status" class="m-0">
                        <?php if ($ticket['status'] !== 'Resuelto'): ?>
                            <input type="hidden" name="status" value="Resuelto">
                            <button type="submit" class="btn btn-success btn-sm">
                                <i class="bi bi-check-lg me-1"></i>Marcar como Resuelto
                            </button>
                        <?php else: ?>
                            <input type="hidden" name="status" value="En Proceso">
                            <button type="submit" class="btn btn-outline-warning btn-sm">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Volver a En Proceso
                            </button>
                        <?php endif; ?>
                    </form>

                    <button type="button"
                        class="btn btn-outline-danger btn-sm"
                        data-bs-toggle="modal"
                        data-bs-target="#deleteModal-ticket">
                        <i class="bi bi-trash me-1"></i>Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Messages Thread -->
<div class="row">
    <!-- Main Column: Message Thread -->
    <div class="col-lg-8">
        <h2 class="h5 mb-3">
            <i class="bi bi-chat-left-text"></i>
            Hilo de mensajes (<?= count($messages) ?>)
        </h2>


        <?php
        // Índice del último mensaje entrante con sugerencia IA (para convertirlo en formulario editable)
        $lastSuggestionIndex = null;
        foreach ($messages as $idx => $msg) {
            if (!empty($msg['ai_suggestion']) && ($msg['direction'] ?? 'incoming') === 'incoming') {
                $lastSuggestionIndex = $idx;
            }
        }
        ?>

        <?php foreach ($messages as $index => $message): ?>
            <div class="card message-card mb-3 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <?php if (($message['direction'] ?? 'incoming') === 'outgoing'): ?>
                            <i class="bi bi-reply text-primary"></i>
                        <?php else: ?>
                            <i class="bi bi-person-circle"></i>
                        <?php endif; ?>
                        <strong><?= htmlspecialchars($message['sender_email']) ?></strong>
                    </div>
                    <small class="text-muted">
                        <?= date('d/m/Y H:i', strtotime($message['created_at'])) ?>
                    </small>
                </div>
                <div class="card-body">
                    <div class="message-body" style="white-space: pre-wrap;"><?= htmlspecialchars($message['body_text']) ?></div>
                </div>

                <?php if (!empty($message['ai_suggestion'])): ?>
                    <div class="card-footer ai-panel p-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-robot me-2 text-primary"></i>
                            <strong class="text-primary">Análisis IA</strong>
                            <?php
                            $sentiment = $message['ai_suggestion']['suggested_sentiment'];
                            $sentimentIcon = match($sentiment) {
                                'positivo' => 'emoji-smile',
                                'negativo' => 'emoji-frown',
                                default => 'emoji-neutral'
                            };
                            ?>
                            <span class="ms-3 sentiment-<?= $sentiment ?>">
                                <i class="bi bi-<?= $sentimentIcon ?>"></i>
                                <?= ucfirst($sentiment) ?>
                            </span>
                            <?php if ($message['ai_suggestion']['suggested_category']): ?>
                                <span class="ms-3 badge bg-light text-dark border">
                                    <i class="bi bi-tag"></i>
                                    <?= htmlspecialchars($message['ai_suggestion']['suggested_category']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2">
                            <?php if ($index === $lastSuggestionIndex): ?>
                                <!-- Formulario editable en el último mensaje con IA -->
                                <form method="POST" action="/tickets/<?= (int) $ticket['id'] ?>/reply" onsubmit="var b=this.querySelector('button[type=submit]');b.disabled=true;b.innerHTML='<i class=\'bi bi-send me-1\'></i>Enviando...';">
                                    <label for="reply_body" class="form-label text-muted mb-1" style="font-size: 0.85rem;">
                                        <i class="bi bi-send me-1"></i>Respuesta sugerida — editable y lista para enviar:
                                    </label>
                                    <textarea
                                        id="reply_body"
                                        name="reply_body"
                                        class="form-control"
                                        rows="7"
                                    ><?= htmlspecialchars($message['ai_suggestion']['suggested_response']) ?></textarea>
                                    <div class="d-flex justify-content-end mt-2">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="bi bi-send me-1"></i> Enviar respuesta
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <small class="text-muted d-block mb-1">Respuesta sugerida:</small>
                                <div class="bg-white rounded p-3 border" style="white-space: pre-wrap;"><?= htmlspecialchars($message['ai_suggestion']['suggested_response']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>


        <?php if ($lastSuggestionIndex === null): ?>
        <!-- Formulario de respuesta fallback cuando no hay sugerencia IA -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <i class="bi bi-reply-fill text-primary"></i>
                <strong>Enviar respuesta al cliente</strong>
            </div>
            <div class="card-body">
                <form method="POST" action="/tickets/<?= (int) $ticket['id'] ?>/reply" onsubmit="var b=this.querySelector('button[type=submit]');b.disabled=true;b.innerHTML='<i class=\'bi bi-send me-1\'></i>Enviando...';">
                    <textarea
                        name="reply_body"
                        class="form-control mb-2"
                        rows="7"
                        placeholder="Escribe tu respuesta aquí..."
                    ></textarea>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-send me-1"></i> Enviar respuesta
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar: AI Summary -->
    <div class="col-lg-4">
        <div class="card shadow-sm sticky-top" style="top: 1rem;">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-robot"></i> Resumen IA
            </div>
            <div class="card-body">
                <?php /* $latestSuggestion provided by TicketController::show() */ ?>

                <?php if ($latestSuggestion): ?>
                    <div class="mb-3">
                        <small class="text-muted d-block">Categoría</small>
                        <span class="badge bg-light text-dark border fs-6">
                            <i class="bi bi-tag"></i>
                            <?= htmlspecialchars($latestSuggestion['suggested_category'] ?? 'Sin categoría') ?>
                        </span>
                    </div>

                    <div>
                        <small class="text-muted d-block">Sentimiento</small>
                        <?php
                        $sentiment = $latestSuggestion['suggested_sentiment'];
                        $sentimentIcon = match($sentiment) {
                            'positivo' => 'emoji-smile',
                            'negativo' => 'emoji-frown',
                            default => 'emoji-neutral'
                        };
                        $sentimentBg = match($sentiment) {
                            'positivo' => 'success',
                            'negativo' => 'danger',
                            default => 'secondary'
                        };
                        ?>
                        <span class="badge bg-<?= $sentimentBg ?> fs-6">
                            <i class="bi bi-<?= $sentimentIcon ?>"></i>
                            <?= ucfirst($sentiment) ?>
                        </span>
                    </div>

                <?php else: ?>
                    <p class="text-muted mb-0">
                        <i class="bi bi-info-circle"></i>
                        No hay análisis de IA disponible para este ticket.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmación de borrado de ticket -->
<div class="modal fade" id="deleteModal-ticket" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle text-danger me-2"></i>Eliminar ticket</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>¿Seguro que quieres eliminar este ticket y todos sus mensajes?</p>
                <p class="fw-semibold"><?= htmlspecialchars($ticket['subject']) ?></p>
                <p class="text-muted small mb-0">Esta acción no se puede deshacer.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="POST" action="/tickets/<?= (int) $ticket['id'] ?>/delete" class="d-inline">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
