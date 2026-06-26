<?php

/**
 * Dashboard legacy — incidentes abiertos con filtros y paginación.
 *
 * Uso: php -S localhost:8080 examples/legacy_dashboard.php
 */

require_once __DIR__ . '/../src/IncidentClient.php';
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Models/Incident.php';
require_once __DIR__ . '/../src/Exceptions/IncidentClientException.php';
require_once __DIR__ . '/../src/Loggers/Logger.php';

use Coordinadora\Legacy\IncidentClient;
use Coordinadora\Legacy\Exceptions\IncidentClientException;

// ─── Parámetros de request ────────────────────────────────────────────────────

$page         = max(1, (int) ($_GET['page']          ?? 1));
$limit        = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
$applicationId = trim($_GET['applicationId'] ?? '');
$severityId    = (int) ($_GET['severityId']  ?? 0);

// ─── Llamada a la API ─────────────────────────────────────────────────────────

$client = new IncidentClient([
    'base_url'  => $_ENV['INCIDENT_API_URL'] ?? 'http://localhost:3001',
    'log_level' => 'error',
]);

$incidents  = [];
$total      = 0;
$totalPages = 1;
$error      = null;

try {
    $filters = ['page' => $page, 'limit' => $limit];

    if ($applicationId !== '') {
        $filters['applicationId'] = $applicationId;
    }
    if ($severityId > 0) {
        $filters['severityId'] = $severityId;
    }

    $result     = $client->getOpenIncidents($filters);
    $incidents  = $result['incidents'];
    $total      = $result['total'];
    $totalPages = $result['totalPages'];
} catch (IncidentClientException $e) {
    $error = $e->getMessage();
} catch (Exception $e) {
    $error = 'Error inesperado: ' . $e->getMessage();
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function pageUrl(int $p): string
{
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinadora — Incidentes Abiertos</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
        }

        header {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        header h1 { font-size: 18px; font-weight: 700; color: #0f172a; }
        header .badge {
            background: #fee2e2;
            color: #b91c1c;
            font-size: 12px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 9999px;
        }

        main { max-width: 1200px; margin: 0 auto; padding: 24px 16px; }

        /* Filtros */
        .filters {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-end;
            margin-bottom: 20px;
        }
        .field { display: flex; flex-direction: column; gap: 4px; }
        .field label { font-size: 12px; font-weight: 500; color: #64748b; }
        .field input, .field select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            color: #1e293b;
            background: #fff;
            outline: none;
            min-width: 180px;
        }
        .field input:focus, .field select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px #dbeafe;
        }
        .filters button {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            border: 1px solid #e2e8f0;
        }
        .btn-primary { background: #3b82f6; color: #fff; border-color: #3b82f6; }
        .btn-primary:hover { background: #2563eb; }
        .btn-ghost { background: #fff; color: #64748b; }
        .btn-ghost:hover { background: #f8fafc; }

        /* Error */
        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        /* Tabla */
        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }
        .card-header {
            padding: 14px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-header span { font-weight: 600; font-size: 15px; }
        .card-header .count {
            margin-left: auto;
            background: #f1f5f9;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 9999px;
        }

        table { width: 100%; border-collapse: collapse; }
        thead { background: #f8fafc; }
        th {
            padding: 10px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #94a3b8;
        }
        td {
            padding: 12px 16px;
            font-size: 13px;
            border-top: 1px solid #f1f5f9;
            color: #334155;
        }
        tr:hover td { background: #f8fafc; }

        .severity {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
        }
        .sev-CRITICAL { background:#fee2e2; color:#b91c1c; }
        .sev-HIGH     { background:#ffedd5; color:#c2410c; }
        .sev-MEDIUM   { background:#fef9c3; color:#854d0e; }
        .sev-LOW      { background:#dcfce7; color:#166534; }
        .sev-INFO     { background:#dbeafe; color:#1d4ed8; }

        .trunc { max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .mono  { font-family: monospace; font-size: 12px; color: #94a3b8; }
        .empty { padding: 48px 0; text-align: center; color: #94a3b8; font-size: 14px; }

        /* Paginación */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-top: 1px solid #f1f5f9;
            font-size: 13px;
            color: #64748b;
            flex-wrap: wrap;
            gap: 8px;
        }
        .pages { display: flex; gap: 4px; }
        .pages a, .pages span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            font-size: 13px;
            text-decoration: none;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        .pages a:hover { background: #f8fafc; }
        .pages .current { background: #3b82f6; color: #fff; border-color: #3b82f6; font-weight: 600; }
        .pages .disabled { opacity: 0.35; pointer-events: none; }
    </style>
</head>
<body>

<header>
    <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#ef4444" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
    </svg>
    <h1>Incidentes Abiertos</h1>
    <span class="badge">LEGACY</span>
</header>

<main>

    <!-- Filtros -->
    <form method="GET" class="filters">
        <div class="field">
            <label for="applicationId">ID de Aplicación</label>
            <input
                type="text"
                id="applicationId"
                name="applicationId"
                value="<?= h($applicationId) ?>"
                placeholder="UUID de la aplicación"
            >
        </div>
        <div class="field">
            <label for="severityId">ID de Severidad</label>
            <input
                type="number"
                id="severityId"
                name="severityId"
                value="<?= $severityId > 0 ? $severityId : '' ?>"
                placeholder="Ej: 1"
                min="1"
            >
        </div>
        <div class="field">
            <label for="limit">Por página</label>
            <select id="limit" name="limit">
                <?php foreach ([10, 20, 50, 100] as $opt): ?>
                    <option value="<?= $opt ?>" <?= $limit === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-primary">Filtrar</button>
        <?php if ($applicationId !== '' || $severityId > 0): ?>
            <a href="?" style="text-decoration:none">
                <button type="button" class="btn-ghost">Limpiar</button>
            </a>
        <?php endif; ?>
    </form>

    <!-- Error -->
    <?php if ($error !== null): ?>
        <div class="error-box">⚠ <?= h($error) ?></div>
    <?php endif; ?>

    <!-- Tabla -->
    <div class="card">
        <div class="card-header">
            <span>Incidentes</span>
            <span class="count"><?= $total ?></span>
        </div>

        <?php if (empty($incidents)): ?>
            <div class="empty">No se encontraron incidentes abiertos.</div>
        <?php else: ?>
            <div style="overflow-x:auto">
                <table>
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Aplicación</th>
                            <th>Severidad</th>
                            <th>Asignado</th>
                            <th>Creado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incidents as $inc): ?>
                            <tr>
                                <td>
                                    <div class="trunc"><?= h($inc->getTitle()) ?></div>
                                    <div class="mono"><?= h(substr($inc->getId(), 0, 8)) ?>…</div>
                                </td>
                                <td><?= h($inc->getApplicationName() ?? '—') ?></td>
                                <td>
                                    <?php
                                        $sev   = strtoupper($inc->getSeverityName() ?? 'INFO');
                                        $color = $inc->getSeverityColor();
                                    ?>
                                    <?php if ($color !== null): ?>
                                        <span class="severity" style="
                                            background-color: <?= h($color) ?>1a;
                                            color: <?= h($color) ?>;
                                            box-shadow: 0 0 0 1px <?= h($color) ?>4d;
                                        "><?= h($sev) ?></span>
                                    <?php else: ?>
                                        <span class="severity sev-<?= h($sev) ?>"><?= h($sev) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= h($inc->getAssignedToName() ?? 'Sin asignar') ?></td>
                                <td><?= h($inc->getCreatedAt()->format('d/m/Y H:i')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="pagination">
                <span>
                    Mostrando <?= (($page - 1) * $limit) + 1 ?>–<?= min($page * $limit, $total) ?>
                    de <?= $total ?> incidentes
                </span>
                <div class="pages">
                    <a href="<?= pageUrl(1) ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>">«</a>
                    <a href="<?= pageUrl($page - 1) ?>" class="<?= $page <= 1 ? 'disabled' : '' ?>">‹</a>

                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    for ($p = $start; $p <= $end; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="current"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= pageUrl($p) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <a href="<?= pageUrl($page + 1) ?>" class="<?= $page >= $totalPages ? 'disabled' : '' ?>">›</a>
                    <a href="<?= pageUrl($totalPages) ?>" class="<?= $page >= $totalPages ? 'disabled' : '' ?>">»</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

</main>
</body>
</html>
