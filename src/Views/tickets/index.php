<?php
/**
 * Vista: Listado de Tickets
 * Variables: $tickets, $filters, $categories, $sentiments
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="bi bi-inbox"></i> Bandeja de Tickets
    </h1>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-secondary fs-6"><?= count($tickets) ?> tickets</span>
        <form method="POST" action="/ingest/sync" class="d-inline" onsubmit="var b=this.querySelector('button[type=submit]');b.disabled=true;b.innerHTML='<i class=\'bi bi-arrow-repeat me-1\'></i>Sincronizando...';b.classList.replace('btn-success','btn-secondary');">
            <button type="submit" class="btn btn-success btn-sm">
                <i class="bi bi-arrow-repeat me-1"></i>Sincronizar correos
            </button>
        </form>
    </div>
</div>

<?php
    $flashSuccess = $_SESSION['flash_success'] ?? null;
    $flashError   = $_SESSION['flash_error']   ?? null;
    unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>

<?php if ($flashSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($flashSuccess) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($flashError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($flashError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Formulario de búsqueda y filtros -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="/tickets" class="row g-2 align-items-end">
            <!-- Búsqueda de texto libre -->
            <div class="col-md-3">
                <label for="search" class="form-label mb-1"><i class="bi bi-search me-1"></i>Buscar</label>
                <input
                    type="text"
                    id="search"
                    name="search"
                    class="form-control form-control-sm"
                    placeholder="Asunto o remitente..."
                    value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                >
            </div>

            <!-- Filtro por estado -->
            <div class="col-md-2">
                <label for="status" class="form-label mb-1">Estado</label>
                <select id="status" name="status" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['No leído', 'En Proceso', 'Resuelto'] as $st): ?>
                        <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>>
                            <?= $st ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filtro por categoría IA -->
            <div class="col-md-2">
                <label for="category" class="form-label mb-1">Categoría IA</label>
                <select id="category" name="category" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= ($filters['category'] ?? '') === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filtro por sentimiento IA -->
            <div class="col-md-2">
                <label for="sentiment" class="form-label mb-1">Sentimiento</label>
                <select id="sentiment" name="sentiment" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($sentiments as $sent): ?>
                        <option value="<?= htmlspecialchars($sent) ?>" <?= ($filters['sentiment'] ?? '') === $sent ? 'selected' : '' ?>>
                            <?= ucfirst(htmlspecialchars($sent)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Botones -->
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">
                    <i class="bi bi-funnel me-1"></i>Filtrar
                </button>
                <a href="/tickets" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-circle me-1"></i>Limpiar
                </a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($tickets)): ?>
    <div class="alert alert-info d-flex align-items-center" role="alert">
        <i class="bi bi-info-circle me-2"></i>
        No hay tickets que coincidan con los filtros aplicados.
    </div>
<?php else: ?>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <?php
                $activeSort = $filters['sort'] ?? '';
                $activeDir  = strtolower($filters['dir'] ?? 'desc');
                $sortCols = ['id', 'subject', 'status', 'ai_category', 'ai_sentiment', 'message_count', 'updated_at'];

                function sortLink(string $col, string $label, string $activeSort, string $activeDir, array $filters): string {
                    $isActive = $activeSort === $col;
                    $nextDir  = ($isActive && $activeDir === 'asc') ? 'desc' : 'asc';
                    $icon     = $isActive
                        ? ($activeDir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down')
                        : 'bi-arrow-down-up';
                    $params = array_filter([
                        'search'    => $filters['search'] ?? '',
                        'status'    => $filters['status'] ?? '',
                        'category'  => $filters['category'] ?? '',
                        'sentiment' => $filters['sentiment'] ?? '',
                        'sort'      => $col,
                        'dir'       => $nextDir,
                    ], fn($v) => $v !== '');
                    $url = '/tickets?' . http_build_query($params);
                    return '<a href="' . htmlspecialchars($url) . '" class="text-white text-decoration-none">'
                        . htmlspecialchars($label)
                        . ' <i class="bi ' . $icon . '"></i></a>';
                }
                ?>
                <thead class="table-dark">
                    <tr>
                        <th scope="col" style="cursor:pointer"><?= sortLink('id', '#', $activeSort, $activeDir, $filters) ?></th>
                        <th scope="col" style="cursor:pointer"><?= sortLink('subject', 'Asunto', $activeSort, $activeDir, $filters) ?></th>
                        <th scope="col" style="cursor:pointer"><?= sortLink('status', 'Estado', $activeSort, $activeDir, $filters) ?></th>
                        <th scope="col" style="cursor:pointer"><?= sortLink('ai_category', 'Categoría IA', $activeSort, $activeDir, $filters) ?></th>
                        <th scope="col" style="cursor:pointer"><?= sortLink('ai_sentiment', 'Sentimiento', $activeSort, $activeDir, $filters) ?></th>
                        <th scope="col" style="cursor:pointer"><?= sortLink('message_count', 'Mensajes', $activeSort, $activeDir, $filters) ?></th>
                        <th scope="col" style="cursor:pointer"><?= sortLink('updated_at', 'Última actividad', $activeSort, $activeDir, $filters) ?></th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr role="button" onclick="window.location='/tickets/<?= $ticket['id'] ?>'" style="cursor:pointer;">
                            <td><strong><?= $ticket['id'] ?></strong></td>
                            <td>
                                <a href="/tickets/<?= $ticket['id'] ?>" class="text-decoration-none text-dark fw-semibold">
                                    <?= htmlspecialchars($ticket['subject']) ?>
                                </a>
                                <?php if (!empty($ticket['has_new_message'])): ?>
                                    <span class="badge bg-primary ms-1">
                                        <i class="bi bi-envelope-fill me-1"></i>Nuevo
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = match($ticket['status']) {
                                    'No leído'             => 'secondary',
                                    'En Proceso'           => 'warning',
                                    'Resuelto'             => 'success',
                                    default                => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?= $statusClass ?>">
                                    <?= htmlspecialchars($ticket['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($ticket['ai_category']): ?>
                                    <span class="badge bg-light text-dark border">
                                        <i class="bi bi-tag"></i>
                                        <?= htmlspecialchars($ticket['ai_category']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($ticket['ai_sentiment']): ?>
                                    <?php
                                    $sentimentIcon = match($ticket['ai_sentiment']) {
                                        'positivo' => 'emoji-smile',
                                        'negativo' => 'emoji-frown',
                                        default => 'emoji-neutral'
                                    };
                                    ?>
                                    <span class="sentiment-<?= $ticket['ai_sentiment'] ?>">
                                        <i class="bi bi-<?= $sentimentIcon ?>"></i>
                                        <?= ucfirst($ticket['ai_sentiment']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-chat-dots"></i> <?= $ticket['message_count'] ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?= date('d/m/Y H:i', strtotime($ticket['updated_at'])) ?>
                                </small>
                            </td>
                            <td onclick="event.stopPropagation()">
                                <button type="button"
                                    class="btn btn-outline-danger btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteModal-<?= $ticket['id'] ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modales de confirmación de borrado (uno por ticket) -->
    <?php foreach ($tickets as $ticket): ?>
        <div class="modal fade" id="deleteModal-<?= $ticket['id'] ?>" tabindex="-1" aria-hidden="true">
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
                        <form method="POST" action="/tickets/<?= $ticket['id'] ?>/delete" class="d-inline">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-trash me-1"></i>Eliminar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
