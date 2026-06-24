# Legacy System Integration (PHP)

Cliente PHP para integración con la plataforma moderna de gestión de incidentes (NestJS/PostgreSQL).

Mantiene compatibilidad entre sistemas legacy (PHP) y moderna sin modificar código existente.

## Descripción

Componente PHP que:
- Consulta incidentes, alertas, eventos desde la API moderna
- Proporciona interfaz familiar para código legacy
- Incluye manejo de errores, reintentos, logging
- Type-safe con PHP 8.0+ + PHPStan

## Requisitos

- PHP 8.0+
- PHP extensions: curl, json
- Composer 2.0+

## Instalación

```bash
cd legacy
composer install
```

## Configuración

### Variables de Entorno

```bash
cp .env.example .env
```

**Editar `.env`:**

```env
# API
INCIDENT_API_URL=http://localhost:3001/api
INCIDENT_API_TIMEOUT=10

# Logging
LOG_LEVEL=info
LOG_PATH=var/log/legacy-integration.log
```

### Programmatically

```php
use Coordinadora\Legacy\IncidentClient;

$client = new IncidentClient([
    'base_url' => 'http://localhost:3001/api',
    'timeout' => 10,
    'verify_ssl' => true,
]);
```

## Uso

### Listar Incidentes

```php
use Coordinadora\Legacy\IncidentClient;

$client = new IncidentClient();

try {
    $incidents = $client->getIncidents([
        'page' => 1,
        'limit' => 50,
    ]);

    foreach ($incidents as $incident) {
        echo sprintf(
            "ID: %s | App: %s | Severity: %s | Status: %s | Created: %s\n",
            $incident->getId(),
            $incident->getApplicationName(),
            $incident->getSeverityName(),
            $incident->getStatusName(),
            $incident->getCreatedAt()->format('Y-m-d H:i:s')
        );
    }
} catch (IncidentClientException $e) {
    error_log("Error fetching incidents: " . $e->getMessage());
}
```

### Filtrar por Aplicación

```php
$incidents = $client->getIncidents([
    'applicationId' => 'app-uuid-123',
    'page' => 1,
    'limit' => 20,
]);
```

### Obtener Alertas

```php
$alerts = $client->getAlerts([
    'page' => 1,
    'limit' => 50,
    'severity' => 5,  // CRITICAL
]);
```

### Obtener Eventos

```php
$events = $client->getEvents([
    'page' => 1,
    'limit' => 100,
    'applicationId' => 'app-uuid-123',
    'severity' => 4,  // HIGH
    'dateFrom' => '2026-06-20',
    'dateTo' => '2026-06-23',
]);
```

### Cambiar Estado de Incidente

```php
try {
    $updated = $client->updateIncidentStatus(
        'incident-uuid-123',
        'IN_PROGRESS',
        'Started investigating root cause'
    );
    
    echo "Estado actualizado a: " . $updated->getStatusName();
} catch (IncidentClientException $e) {
    error_log("Error updating status: " . $e->getMessage());
}
```

## Estructura

```
legacy/
├── src/
│   ├── IncidentClient.php                  # Cliente HTTP principal
│   ├── Config.php                          # Gestión de configuración
│   ├── Models/
│   │   ├── Incident.php                    # DTO Incident
│   │   ├── Alert.php                       # DTO Alert
│   │   └── Event.php                       # DTO Event
│   ├── Exceptions/
│   │   └── IncidentClientException.php     # Excepciones personalizadas
│   └── Loggers/
│       └── Logger.php                      # Logging simple
├── docs/
│   ├── README.md
│   └── INTEGRATION.md                      # Detalles de integración
├── examples/
│   ├── fetch_incidents.php                 # Ejemplo: listar incidentes
│   ├── fetch_alerts.php                    # Ejemplo: listar alertas
│   ├── fetch_events.php                    # Ejemplo: listar eventos
│   └── update_status.php                   # Ejemplo: cambiar estado
├── tests/
│   └── IncidentClientTest.php              # Tests unitarios
├── composer.json
├── .env.example
├── phpstan.neon                            # Configuración PHPStan
└── README.md
```

## API Endpoints Consumidos

### GET /incidents

Lista incidentes paginados.

**Parámetros:**
- `page` (int, default: 1)
- `limit` (int, default: 10)

**Response:**
```json
{
  "items": [
    {
      "id": "incident-uuid",
      "title": "Database Connection Timeout",
      "description": "Connection pool exhausted, all 50 connections are in use",
      "applicationId": "app-uuid-123",
      "applicationName": "payment-service",
      "severityId": 5,
      "severityName": "CRITICAL",
      "statusName": "OPEN",
      "assignedToId": "user-uuid-456",
      "assignedToName": "John Doe",
      "createdAt": "2026-06-22T10:30:00Z"
    }
  ],
  "total": 42
}
```

### GET /alerts

Lista alertas paginadas.

**Parámetros:**
- `page` (int, default: 1)
- `limit` (int, default: 10)
- `severity` (int, optional) - 1=INFO, 2=LOW, 3=MEDIUM, 4=HIGH, 5=CRITICAL
- `statusId` (int, optional)
- `applicationId` (string, optional)

**Response:** Similar a incidents

### GET /events

Lista eventos con filtros avanzados.

**Parámetros:**
- `page` (int, default: 1)
- `limit` (int, default: 10)
- `applicationId` (string, optional)
- `severity` (int, optional)
- `eventTypeId` (int, optional)
- `dateFrom` (ISO8601, optional)
- `dateTo` (ISO8601, optional)

**Response:**
```json
{
  "items": [
    {
      "id": "event-uuid",
      "applicationId": "app-uuid-123",
      "applicationName": "payment-service",
      "eventTypeId": 1,
      "eventTypeName": "ERROR",
      "severityId": 4,
      "severityName": "HIGH",
      "description": "NullPointerException in UserService.getUser() method",
      "traceId": "trace-789abc-12345",
      "occurredAt": "2026-06-23T14:30:00Z",
      "createdAt": "2026-06-23T14:30:05Z"
    }
  ],
  "total": 215
}
```

### PATCH /incidents/:id/update-status

Cambia estado de incidente.

**Body:**
```json
{
  "newStatus": "IN_PROGRESS",
  "reason": "Started investigating root cause with backend team"
}
```

**Response:**
```json
{
  "id": "incident-uuid",
  "status": "IN_PROGRESS",
  "updatedAt": "2026-06-23T14:35:00Z"
}
```

## Manejo de Errores

```php
use Coordinadora\Legacy\IncidentClient;
use Coordinadora\Legacy\Exceptions\IncidentClientException;

$client = new IncidentClient();

try {
    $incidents = $client->getIncidents(['page' => 1]);
} catch (IncidentClientException $e) {
    // Errores de API: 4xx, 5xx, timeout, conexión
    error_log($e->getMessage());
    error_log($e->getStatusCode());  // HTTP status code
    
    // Retry logic
    sleep(2);
    // retry...
}
```

## Logging

Logs en `var/log/legacy-integration.log`. Nivel configurable:

```php
$client = new IncidentClient();
$client->setLogLevel('debug');  // debug, info, warning, error
```

## Models (DTOs)

### Incident

```php
$incident->getId()                 // string
$incident->getTitle()              // string
$incident->getDescription()        // string
$incident->getApplicationId()      // string
$incident->getApplicationName()    // string
$incident->getSeverityId()         // int
$incident->getSeverityName()       // string
$incident->getStatusName()         // string
$incident->getAssignedToId()       // ?string
$incident->getAssignedToName()     // ?string
$incident->getCreatedAt()          // DateTime
```

### Alert

```php
$alert->getId()                    // string
$alert->getSourceEventId()         // string
$alert->getSeverityName()          // string
$alert->getStatusName()            // string
$alert->getApplicationName()       // string
$alert->getCreatedAt()             // DateTime
```

### Event

```php
$event->getId()                    // string
$event->getApplicationId()         // string
$event->getApplicationName()       // string
$event->getEventTypeName()         // string
$event->getSeverityName()          // string
$event->getDescription()           // string
$event->getTraceId()               // string
$event->getOccurredAt()            // DateTime
$event->getCreatedAt()             // DateTime
```

## Type Safety

Usar PHPStan para validación de tipos:

```bash
composer run phpstan
```

Configuración en `phpstan.neon`: Level 7 (máximo)

## Testing

```bash
composer test              # Correr tests
composer test -- --watch   # Watch mode
```

Usa PHPUnit. Tests en `tests/IncidentClientTest.php`.

## Scripting

Validar incidentes en BD:

```bash
php bin/validate.php
```

## Integración

**Requisitos:**
1. API moderna (NestJS) corriendo en `INCIDENT_API_URL`
2. PostgreSQL con datos sincronizados
3. Opcional: Redis para caching

**Flujo:**
```
Legacy PHP Code
    ↓
IncidentClient (HTTP GET)
    ↓
API REST (NestJS)
    ↓
PostgreSQL
    ↓
Response JSON
    ↓
Models (DTOs)
    ↓
Legacy Code (Data)
```

## Performance

- **Timeout:** 10s configurable
- **Retry:** Manual (implementar en código legacy)
- **Paginación:** Implementar cursor-based si es necesario
- **Caching:** Considerar Redis para datos estáticos (aplicaciones, severidades)

## Consideraciones

✅ **Hacer:**
- Validar page/limit antes de enviar
- Implementar reintentos exponenciales
- Loguear errores de integración
- Caching de datos maestros (apps, severidades)

❌ **NO hacer:**
- Llamadas en loops sin paginación
- Ignorar excepciones
- Queries sin límite
- Cambiar estado sin validar

## Licencia

Coordinadora (Internal Use)
