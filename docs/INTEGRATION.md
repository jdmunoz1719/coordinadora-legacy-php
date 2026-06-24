# Detalles de Integración - Cliente PHP Legacy

Documentación técnica para integrar el cliente PHP con la API moderna de gestión de incidentes.

## Flujo de Integración

```
Código Legacy (PHP)
    ↓
IncidentClient::getIncidents()
    ↓
HTTP GET /api/incidents?page=1&limit=50
    ↓
API REST (NestJS + PostgreSQL)
    ↓
Response JSON con datos estructurados
    ↓
IncidentClient parsea → Incident DTOs
    ↓
Código Legacy usa Incident objects
```

## Endpoints Consumidos

### GET /incidents

Lista incidentes paginados.

**Request:**
```
GET http://localhost:3001/api/incidents?page=1&limit=50
```

**Query Parameters:**
- `page` (int, default: 1) - Número de página
- `limit` (int, default: 10, max: 100) - Items por página

**Response (200 OK):**
```json
{
  "items": [
    {
      "id": "incident-uuid",
      "title": "Database Connection Timeout",
      "description": "Connection pool exhausted",
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

**Error (5xx):**
- Reintentar con backoff exponencial
- Máximo 3 reintentos
- Intervalo: 1s → 2s → 4s

### PATCH /incidents/:id/update-status

Cambia estado de incidente.

**Request:**
```
PATCH http://localhost:3001/api/incidents/incident-uuid/update-status
Content-Type: application/json

{
  "newStatus": "IN_PROGRESS",
  "reason": "Started investigation"
}
```

**Parameters:**
- `newStatus` (string) - Nuevo estado (OPEN, IN_PROGRESS, RESOLVED)
- `reason` (string) - Motivo del cambio

**Response (200 OK):**
```json
{
  "id": "incident-uuid",
  "statusName": "IN_PROGRESS",
  "updatedAt": "2026-06-23T14:35:00Z"
}
```

**Errores:**
- 404: Incidente no encontrado
- 400: Status inválido
- 5xx: Reintentar

## Configuración de Cliente

### Por Ambiente

```php
// .env
INCIDENT_API_URL=http://localhost:3001/api
INCIDENT_API_TIMEOUT=10
LOG_LEVEL=info
LOG_PATH=var/log/legacy-integration.log
```

### Desarrollo
```
INCIDENT_API_URL=http://localhost:3001/api
INCIDENT_API_TIMEOUT=10
LOG_LEVEL=debug
```

### Producción
```
INCIDENT_API_URL=https://api.coordinadora.com/api
INCIDENT_API_TIMEOUT=30
LOG_LEVEL=warning
```

## Manejo de Errores

### Reintentos Automáticos

Client reintenta en:
- HTTP 408 (Request Timeout)
- HTTP 429 (Too Many Requests)
- HTTP 500, 502, 503 (Server Errors)
- Connection timeout

**Estrategia:**
```
Intento 1: 1s delay
Intento 2: 2s delay
Intento 3: 4s delay
Falla: throw IncidentClientException
```

### Excepciones

```php
try {
    $incidents = $client->getIncidents(['page' => 1]);
} catch (IncidentClientException $e) {
    // $e->getMessage()       - Error message
    // $e->getStatusCode()    - HTTP status code (null si network error)
    // $e->getPrevious()      - Causa original
    
    error_log($e->getMessage());
    
    // Implementar lógica de fallback
    // - Usar cache
    // - Datos por defecto
    // - Alertar admin
}
```

## Performance

### Paginación

Usar paginación para queries grandes:

```php
// MAL: Sin paginación
$allIncidents = [];
for ($page = 1; $page <= 100; $page++) {
    $allIncidents = array_merge(
        $allIncidents,
        $client->getIncidents(['page' => $page, 'limit' => 100])
    );
}

// BIEN: Con límite
$incidents = $client->getIncidents([
    'page' => 1,
    'limit' => 50,
]);

// Procesar items
foreach ($incidents as $incident) {
    // ...
}

// Cargar siguiente página si es necesario
if (count($incidents) >= 50) {
    $nextPage = $client->getIncidents([
        'page' => 2,
        'limit' => 50,
    ]);
}
```

### Caching

Consideraciones:
- Cachear datos maestros (app names, severities) por horas
- Cachear lista de incidentes por minutos
- Invalidar cache en cambios de estado

```php
$cacheKey = 'incidents_page_1_limit_50';
$ttl = 300; // 5 minutos

if ($cache->has($cacheKey)) {
    $incidents = $cache->get($cacheKey);
} else {
    $incidents = $client->getIncidents(['page' => 1, 'limit' => 50]);
    $cache->set($cacheKey, $incidents, $ttl);
}
```

## Logging

### Niveles

```
debug   - Requests/responses detallados
info    - Operaciones exitosas
warning - Reintentos, errores recuperables
error   - Errores no recuperables
```

### Uso

```php
$client->setLogLevel('debug');

// Log automático en:
// - Cada request HTTP
// - Reintentos
// - Errores
// - Parsing de responses
```

### Archivo Log

```
var/log/legacy-integration.log

Formato:
[2026-06-24 14:35:00] INFO: GET /incidents - Status 200 - 145ms
[2026-06-24 14:35:05] WARN: Retry attempt 1/3 - Status 503
[2026-06-24 14:35:06] ERROR: IncidentClientException - Connection timeout
```

## Validación de Datos

### Incident DTO

```php
$incident->getId()              // string - UUID
$incident->getTitle()           // string
$incident->getDescription()     // string
$incident->getApplicationId()   // string - UUID
$incident->getApplicationName() // string
$incident->getSeverityId()      // int - 1-5
$incident->getSeverityName()    // string - INFO, LOW, MEDIUM, HIGH, CRITICAL
$incident->getStatusName()      // string - OPEN, IN_PROGRESS, RESOLVED
$incident->getAssignedToId()    // ?string - Puede ser null
$incident->getAssignedToName()  // ?string - Puede ser null
$incident->getCreatedAt()       // DateTime
```

### Validación Recomendada

```php
if (!$incident->getId()) {
    throw new InvalidArgumentException('Incident ID is required');
}

if (!in_array($incident->getSeverityName(), ['CRITICAL', 'HIGH', 'MEDIUM', 'LOW', 'INFO'])) {
    throw new InvalidArgumentException('Invalid severity');
}

if (!in_array($incident->getStatusName(), ['OPEN', 'IN_PROGRESS', 'RESOLVED'])) {
    throw new InvalidArgumentException('Invalid status');
}
```

## Testing

### Unit Tests

```bash
composer test
```

Cubre:
- IncidentClient::getIncidents()
- IncidentClient::updateIncidentStatus()
- Manejo de errores
- Parsing de responses
- Reintentos

### Integration Tests (Manual)

1. Verificar API está corriendo:
   ```bash
   curl http://localhost:3001/api/incidents
   ```

2. Ejecutar ejemplo:
   ```bash
   php examples/fetch_incidents.php
   ```

3. Validar logs:
   ```bash
   tail -f var/log/legacy-integration.log
   ```

## Troubleshooting

### "Connection refused"
- API no está corriendo
- URL incorrecta en .env
- Firewall bloqueando puerto 3001

**Solución:**
```bash
# Verificar API
curl -i http://localhost:3001/api/incidents

# Actualizar .env
INCIDENT_API_URL=http://localhost:3001/api
```

### "HTTP 401 Unauthorized"
- Sin autenticación implementada aún
- Verificar headers requeridos

### "HTTP 429 Too Many Requests"
- Rate limiting activo
- Reducir frecuencia de requests
- Implementar caché

### "Timeout"
- INCIDENT_API_TIMEOUT muy bajo
- Aumentar en .env: `INCIDENT_API_TIMEOUT=30`
- Verificar latencia de red

## Roadmap

Funcionalidades futuras:
- [ ] Soporte para autenticación (JWT, API Key)
- [ ] Caché integrado (Redis)
- [ ] Bulk operations
- [ ] WebSocket para actualizaciones en tiempo real
- [ ] ORM/Query builder
