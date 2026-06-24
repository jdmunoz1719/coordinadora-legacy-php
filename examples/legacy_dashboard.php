<?php

/**
 * Ejemplo: Dashboard legacy que muestra incidentes críticos en tiempo real.
 *
 * Simula un sistema PHP legacy que necesita datos de incidentes modernos.
 * - Cachea en memoria durante 5 min para no saturar API
 * - Muestra estadísticas por severidad
 * - Manejo de errores graceful
 */

require_once __DIR__ . '/../src/IncidentClient.php';
require_once __DIR__ . '/../src/Config.php';
require_once __DIR__ . '/../src/Models/Incident.php';
require_once __DIR__ . '/../src/Exceptions/IncidentClientException.php';
require_once __DIR__ . '/../src/Loggers/Logger.php';

use Coordinadora\Legacy\IncidentClient;
use Coordinadora\Legacy\Exceptions\IncidentClientException;

class IncidentDashboard
{
    private IncidentClient $client;
    private string $cacheFile;
    private int $cacheTtl = 300; // 5 minutos

    public function __construct()
    {
        $this->client = new IncidentClient([
            'base_url' => $_ENV['INCIDENT_API_URL'] ?? 'http://localhost:3001',
            'log_level' => $_ENV['LOG_LEVEL'] ?? 'info',
        ]);

        $this->cacheFile = sys_get_temp_dir() . '/incidents_cache.json';
    }

    /**
     * Retorna incidentes (del cache si es válido, o consulta API).
     */
    public function getIncidents(int $page = 1, int $limit = 50): array
    {
        // Intentar cache primero
        if ($this->isCacheValid()) {
            return json_decode(file_get_contents($this->cacheFile), true);
        }

        try {
            $incidents = $this->client->getOpenIncidents([
                'page' => $page,
                'limit' => $limit,
            ]);

            // Cachear resultados
            $data = array_map(fn($inc) => $inc->toArray(), $incidents);
            file_put_contents(
                $this->cacheFile,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            return $data;
        } catch (IncidentClientException $e) {
            // Fallback a cache antiguo si API falla
            if (file_exists($this->cacheFile)) {
                error_log("API failed, using stale cache: " . $e->getMessage());
                return json_decode(file_get_contents($this->cacheFile), true) ?? [];
            }
            throw $e;
        }
    }

    /**
     * Estadísticas por severidad.
     */
    public function getStatistics(array $incidents): array
    {
        $stats = [
            'total' => count($incidents),
            'by_severity' => [],
            'by_application' => [],
        ];

        foreach ($incidents as $incident) {
            $severity = $incident['severityName'] ?? 'UNKNOWN';
            $app = $incident['applicationName'] ?? 'Unknown';

            $stats['by_severity'][$severity] = ($stats['by_severity'][$severity] ?? 0) + 1;
            $stats['by_application'][$app] = ($stats['by_application'][$app] ?? 0) + 1;
        }

        // Ordenar por count desc
        arsort($stats['by_severity']);
        arsort($stats['by_application']);

        return $stats;
    }

    private function isCacheValid(): bool
    {
        if (!file_exists($this->cacheFile)) {
            return false;
        }

        $age = time() - filemtime($this->cacheFile);
        return $age < $this->cacheTtl;
    }
}

// ============================================================================
// HTML Output
// ============================================================================

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coordinadora - Dashboard Legacy</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; }
        .metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
        .metric { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .metric-value { font-size: 32px; font-weight: bold; color: #2196F3; }
        .metric-label { color: #666; margin-top: 8px; }
        table { width: 100%; border-collapse: collapse; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        thead { background: #2196F3; color: white; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f9f9f9; }
        .severity-CRITICAL { color: #d32f2f; font-weight: bold; }
        .severity-HIGH { color: #f57c00; font-weight: bold; }
        .severity-MEDIUM { color: #fbc02d; }
        .severity-LOW { color: #388e3c; }
        .severity-INFO { color: #1976d2; }
        .error { background: #ffebee; color: #c62828; padding: 16px; border-radius: 4px; margin: 20px 0; }
        .footer { color: #999; font-size: 12px; margin-top: 40px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚨 Coordinadora - Centro de Control Incidentes (Legacy)</h1>

        <?php
        try {
            $dashboard = new IncidentDashboard();
            $incidents = $dashboard->getIncidents();
            $stats = $dashboard->getStatistics($incidents);

            // Mostrar métricas
            echo '<div class="metrics">';
            echo '<div class="metric">';
            echo '<div class="metric-value">' . $stats['total'] . '</div>';
            echo '<div class="metric-label">Incidentes Abiertos</div>';
            echo '</div>';

            foreach ($stats['by_severity'] as $severity => $count) {
                echo '<div class="metric">';
                echo '<div class="metric-value severity-' . $severity . '">' . $count . '</div>';
                echo '<div class="metric-label">' . $severity . '</div>';
                echo '</div>';
            }
            echo '</div>';

            // Tabla de incidentes
            echo '<h2>Incidentes Críticos</h2>';
            echo '<table>';
            echo '<thead><tr>';
            echo '<th>ID</th>';
            echo '<th>Aplicación</th>';
            echo '<th>Severidad</th>';
            echo '<th>Asignado</th>';
            echo '<th>Creado</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach (array_slice($incidents, 0, 20) as $incident) {
                $severity = $incident['severityName'] ?? '?';
                echo '<tr>';
                echo '<td><code>' . substr($incident['id'], 0, 8) . '...</code></td>';
                echo '<td>' . htmlspecialchars($incident['applicationName'] ?? 'Unknown') . '</td>';
                echo '<td><span class="severity-' . $severity . '">' . $severity . '</span></td>';
                echo '<td>' . htmlspecialchars($incident['assignedToName'] ?? 'Sin asignar') . '</td>';
                echo '<td>' . htmlspecialchars($incident['createdAt']) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            echo '<div class="footer">Actualización: ' . date('Y-m-d H:i:s') . ' | Datos desde plataforma moderna</div>';
        } catch (IncidentClientException $e) {
            echo '<div class="error">';
            echo '<strong>Error de conexión:</strong> ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>Error inesperado:</strong> ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>
