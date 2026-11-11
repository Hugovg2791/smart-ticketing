<?php
/**
 * Vista: Configuración
 * Variables: $isConnected (bool), $flash (?array{type: string, message: string})
 */
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="bi bi-gear"></i> Configuración
    </h1>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-envelope"></i> Integración con Gmail</h5>
    </div>
    <div class="card-body d-flex align-items-center justify-content-between">
        <div>
            <p class="mb-1 fw-semibold">Estado de la conexión</p>
            <?php if ($isConnected): ?>
                <span class="badge bg-success fs-6">
                    <i class="bi bi-check-circle me-1"></i> Conectado
                </span>
                <p class="text-muted small mt-2 mb-0">
                    Gmail está vinculado. La ingesta de correos funcionará correctamente.
                </p>
            <?php else: ?>
                <span class="badge bg-danger fs-6">
                    <i class="bi bi-x-circle me-1"></i> No conectado
                </span>
                <p class="text-muted small mt-2 mb-0">
                    Conecta tu cuenta de Gmail para que el sistema pueda procesar correos entrantes.
                </p>
            <?php endif; ?>
        </div>
        <?php if (!$isConnected): ?>
            <a href="/settings/connect" class="btn btn-primary">
                <i class="bi bi-google me-1"></i> Conectar Gmail
            </a>
        <?php endif; ?>
    </div>
</div>
