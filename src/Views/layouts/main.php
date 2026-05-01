<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Electrotech Smart Ticketing - Panel de gestión de tickets de soporte">
    <title><?= htmlspecialchars($pageTitle ?? 'Electrotech Smart Ticketing') ?> — Electrotech Smart Ticketing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; }
        .navbar-brand i { font-size: 1.3rem; }
        .sentiment-positivo { color: #198754; }
        .sentiment-neutral  { color: #6c757d; }
        .sentiment-negativo { color: #dc3545; }
        .ai-panel { background: #f8f9ff; border-left: 4px solid #6366f1; }
        .message-card { border-left: 3px solid #dee2e6; }
        .message-card:hover { border-left-color: #0d6efd; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="/tickets">
                <i class="bi bi-ticket-detailed"></i>
                <span>Electrotech Smart Ticketing</span>
            </a>
            <div class="navbar-nav ms-auto">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a class="nav-link active me-3" href="/tickets">
                        <i class="bi bi-inbox"></i> Tickets
                    </a>
                    <a class="nav-link me-3" href="/settings">
                        <i class="bi bi-gear"></i> Configuración
                    </a>
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'Agente') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item text-danger" href="/logout">
                                    <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Content -->
    <main class="container mb-5">
        <?php
        // Render the view content passed as $viewContent
        if (isset($viewContent)) {
            echo $viewContent;
        }
        ?>
    </main>

    <!-- Footer -->
    <footer class="text-center text-muted py-4 border-top">
        <small>&copy; <?= date('Y') ?> Smart Ticketing — TFG DAW</small>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
