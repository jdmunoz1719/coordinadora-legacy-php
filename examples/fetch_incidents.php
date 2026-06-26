<?php

/**
 * Ejemplo básico: Fetch incidentes abiertos
 *
 * Uso:
 *   php examples/fetch_incidents.php [page] [limit]
 *   php examples/fetch_incidents.php 1 50
 */

require_once __DIR__ . '/../src/IncidentClient.php';
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Models/Incident.php';
require_once __DIR__ . '/../src/Exceptions/IncidentClientException.php';
require_once __DIR__ . '/../src/Loggers/Logger.php';

use Coordinadora\Legacy\IncidentClient;
use Coordinadora\Legacy\Exceptions\IncidentClientException;

$page = (int)($argv[1] ?? 1);
$limit = (int)($argv[2] ?? 20);

try {
    $client = new IncidentClient([
        'base_url' => $_ENV['INCIDENT_API_URL'] ?? 'http://localhost:3001',
        'log_level' => 'info',
    ]);

    echo "Fetching open incidents (page $page, limit $limit)...\n";
    echo str_repeat('-', 120) . "\n";

    $result = $client->getOpenIncidents([
        'page' => $page,
        'limit' => $limit,
    ]);

    $incidents = $result['incidents'];

    if (empty($incidents)) {
        echo "No incidents found.\n";
        exit(0);
    }

    // Mostrar en formato tabla
    printf(
        "%-36s | %-20s | %-15s | %-12s | %s\n",
        'ID',
        'Application',
        'Severity',
        'Status',
        'Created'
    );
    echo str_repeat('-', 120) . "\n";

    foreach ($incidents as $incident) {
        printf(
            "%-36s | %-20s | %-15s | %-12s | %s\n",
            substr($incident->getId(), 0, 36),
            substr($incident->getApplicationName() ?? 'Unknown', 0, 20),
            $incident->getSeverityName() ?? '?',
            $incident->getStatusName() ?? 'OPEN',
            $incident->getCreatedAt()->format('Y-m-d H:i')
        );
    }

    echo str_repeat('-', 120) . "\n";
    echo sprintf("Showing %d of %d total (page %d/%d)\n",
        count($incidents),
        $result['total'],
        $result['page'],
        $result['totalPages']
    );
} catch (IncidentClientException $e) {
    fprintf(STDERR, "Error: %s\n", $e->getMessage());
    if ($e->getHttpCode()) {
        fprintf(STDERR, "HTTP Code: %d\n", $e->getHttpCode());
    }
    exit(1);
} catch (Exception $e) {
    fprintf(STDERR, "Unexpected error: %s\n", $e->getMessage());
    exit(1);
}
