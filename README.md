# Legacy — Cliente PHP de Incidentes

Cliente PHP que consume la API moderna de Coordinadora para consultar y visualizar incidentes abiertos. Diseñado para integrarse en sistemas PHP legacy sin modificar su infraestructura.

---

## Estructura

```
legacy/
├── src/
│   ├── IncidentClient.php              # Cliente HTTP principal (cURL)
│   ├── Config.php                      # Configuración desde env vars
│   ├── Models/
│   │   └── Incident.php                # Value object — datos de un incidente
│   ├── Exceptions/
│   │   └── IncidentClientException.php # Excepciones tipadas (HTTP, timeout, red)
│   └── Loggers/
│       └── Logger.php                  # Logger simple (archivo + niveles)
├── examples/
│   ├── fetch_incidents.php             # Script CLI: lista incidentes en tabla
│   └── legacy_dashboard.php            # Dashboard HTML con filtros y paginación
├── tests/
│   └── IncidentTest.php                # Tests del modelo Incident (PHPUnit)
├── bin/
│   └── validate.php                    # Script de validación de configuración
├── docs/
│   └── INTEGRATION.md                  # Documentación técnica del mecanismo de integración
├── composer.json
├── .env.example
├── phpstan.neon
└── README.md
```

---

## Requisitos

- PHP 8.0+
- Extensiones PHP: `curl`, `json`
- Composer 2+

---

## Instalación

```bash
cd legacy
composer install
```

Copiar y configurar variables de entorno:

```bash
cp .env.example .env
```

Editar `.env`:

```env
INCIDENT_API_URL=http://localhost:3001
INCIDENT_API_TIMEOUT=10
INCIDENT_VERIFY_SSL=true
LOG_LEVEL=info
LOG_PATH=/var/log/legacy-integration.log
```

> `INCIDENT_API_URL` debe apuntar a la raíz del backend NestJS (sin `/api`).

---

## Ejecución

### Dashboard web

Levanta un servidor PHP integrado y abre el dashboard en el navegador:

```bash
php -S localhost:8080 examples/legacy_dashboard.php
```

Acceder en: [http://localhost:8080](http://localhost:8080)

El dashboard permite:
- Filtrar por **ID de aplicación** y **ID de severidad**
- Seleccionar cantidad de resultados por página (10 / 20 / 50 / 100)
- Navegar entre páginas con controles de paginación

### Script CLI

Lista incidentes abiertos en formato tabla en la terminal:

```bash
php examples/fetch_incidents.php [page] [limit]

# Ejemplos
php examples/fetch_incidents.php          # página 1, 20 resultados
php examples/fetch_incidents.php 2 50     # página 2, 50 resultados
```

### Validación de configuración

Verifica que la conexión con el API y las variables de entorno estén correctas:

```bash
php bin/validate.php
```

---

## Uso programático

```php
require_once 'vendor/autoload.php';

use Coordinadora\Legacy\IncidentClient;
use Coordinadora\Legacy\Exceptions\IncidentClientException;

$client = new IncidentClient([
    'base_url' => 'http://localhost:3001',
]);

try {
    $result = $client->getOpenIncidents([
        'page'          => 1,
        'limit'         => 20,
        'applicationId' => 'uuid-de-la-app',   // opcional
        'severityId'    => 2,                   // opcional
    ]);

    foreach ($result['incidents'] as $incident) {
        echo $incident->getTitle() . ' — ' . $incident->getSeverityName() . "\n";
    }

    echo "Total: {$result['total']} (página {$result['page']} de {$result['totalPages']})\n";

} catch (IncidentClientException $e) {
    error_log('Error: ' . $e->getMessage());
    error_log('HTTP: ' . $e->getHttpCode());
}
```

### Retorno de `getOpenIncidents()`

```php
[
    'incidents'  => Incident[],  // array de objetos Incident
    'total'      => int,         // total de registros en BD
    'page'       => int,         // página actual
    'limit'      => int,         // resultados por página
    'totalPages' => int,         // total de páginas
]
```

### Métodos del modelo `Incident`

```php
$incident->getId()             // string  — UUID del incidente
$incident->getTitle()          // string  — título
$incident->getDescription()    // string  — descripción
$incident->getApplicationId()  // string  — UUID de la aplicación
$incident->getApplicationName()// ?string — nombre de la aplicación
$incident->getSeverityId()     // int     — ID de severidad
$incident->getSeverityName()   // ?string — CRITICAL | HIGH | MEDIUM | LOW | INFO
$incident->getStatusName()     // ?string — siempre OPEN en este endpoint
$incident->getAssignedToId()   // ?string — UUID del responsable (nullable)
$incident->getAssignedToName() // ?string — nombre del responsable (nullable)
$incident->getCreatedAt()      // DateTimeImmutable
$incident->toArray()           // array   — representación serializable
```

---

## Calidad

### Análisis estático (PHPStan nivel 7)

```bash
composer phpstan
```

### Tests

```bash
composer test
```

### Code style (PSR-12)

```bash
composer phpcs    # verificar
composer phpcbf   # corregir automáticamente
```

---

## Configuración avanzada

| Variable               | Default                              | Descripción                          |
|------------------------|--------------------------------------|--------------------------------------|
| `INCIDENT_API_URL`     | `http://localhost:3001`              | URL base del backend NestJS          |
| `INCIDENT_API_TIMEOUT` | `10`                                 | Timeout en segundos                  |
| `INCIDENT_VERIFY_SSL`  | `true`                               | Verificar certificado SSL            |
| `LOG_LEVEL`            | `info`                               | `debug` / `info` / `warning` / `error` |
| `LOG_PATH`             | `/tmp/legacy-integration.log`        | Ruta del archivo de logs             |

También se puede configurar programáticamente:

```php
$client = new IncidentClient([
    'base_url'   => 'https://api.coordinadora.com',
    'timeout'    => 30,
    'verify_ssl' => true,
    'log_level'  => 'warning',
    'log_path'   => '/var/log/app.log',
]);

$client->setMaxRetries(5);   // reintentos en errores transitorios (default: 3)
$client->setLogLevel('debug');
```
