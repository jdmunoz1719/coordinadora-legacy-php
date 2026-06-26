# Mecanismo de Integración: Legacy PHP ↔ NestJS

## Visión general

El componente legacy es un cliente PHP que consume **un único endpoint** de la API moderna. No tiene base de datos propia ni acceso a otros servicios — toda la lógica de negocio, persistencia y paginación vive en el backend NestJS.

```
┌────────────────────────────────┐        ┌──────────────────────────────────────────────┐
│         LEGACY (PHP)           │        │              MODERNO (NestJS)                 │
│                                │        │                                              │
│  legacy_dashboard.php          │  HTTP  │  GET /api/incidents/open                     │
│  fetch_incidents.php     ──────┼──GET──►│                                              │
│                                │        │  IncidentsController                         │
│  IncidentClient                │        │  └─ ListOpenIncidentsUseCase                 │
│  └─ getOpenIncidents()  ◄──────┼──JSON──│     └─ IncidentPrismaRepository.findAllOpen()│
│     └─ cURL + retry            │        │        └─ PostgreSQL                         │
│        └─ Incident[]           │        │                                              │
└────────────────────────────────┘        └──────────────────────────────────────────────┘
```

---

## Contrato de la API

### Endpoint

```
GET {INCIDENT_API_URL}/api/incidents/open
```

### Query params (todos opcionales)

| Parámetro       | Tipo   | Descripción                               |
|-----------------|--------|-------------------------------------------|
| `page`          | number | Número de página (default: 1)             |
| `limit`         | number | Resultados por página (default: 10)       |
| `applicationId` | string | UUID de la aplicación para filtrar        |
| `severityId`    | number | ID numérico del nivel de severidad        |

### Respuesta exitosa — `200 OK`

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "API de pagos sin respuesta",
      "description": "El servicio de pagos lleva 15 minutos sin responder.",
      "applicationId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
      "applicationName": "Payment Service",
      "severityId": 1,
      "severityName": "CRITICAL",
      "severityColor": "#ef4444",
      "statusName": "OPEN",
      "assignedToId": null,
      "assignedToName": null,
      "createdAt": "2025-06-20T14:32:00.000Z"
    }
  ],
  "total": 42,
  "page": 1,
  "limit": 20,
  "totalPages": 3,
  "hasNextPage": true,
  "hasPreviousPage": false
}
```

> El backend filtra automáticamente por `status = OPEN`. No es posible recibir incidentes con otro estado desde este endpoint.

### Errores posibles

| HTTP | Causa                                              | Comportamiento PHP                          |
|------|----------------------------------------------------|---------------------------------------------|
| 400  | Query params con formato inválido                  | Lanza `IncidentClientException`, sin reintentos |
| 401  | Credenciales inválidas (si auth está activo)       | Lanza `IncidentClientException`, sin reintentos |
| 404  | Ruta incorrecta (fallo de config)                  | Lanza `IncidentClientException`, sin reintentos |
| 429  | Rate limit                                         | Reintenta con backoff exponencial           |
| 500  | Error interno del backend                          | Reintenta hasta `maxRetries` veces          |
| —    | Timeout de red                                     | Reintenta hasta `maxRetries` veces          |
| —    | Error de conexión (DNS, refused)                   | Reintenta hasta `maxRetries` veces          |

---

## Implementación del cliente

### Flujo de `getOpenIncidents()`

```
getOpenIncidents($filters)
│
├─ Construir query string desde $filters
│   applicationId → string (UUID)
│   severityId    → int (>= 1)
│   page          → int (>= 1)
│   limit         → int (1–100)
│
├─ cURL GET {INCIDENT_API_URL}/api/incidents/open?{query}
│   Headers:
│     Accept: application/json
│     Content-Type: application/json
│
├─ ¿HTTP >= 400?
│   ├─ 4xx → throw IncidentClientException (NO reintentar)
│   └─ 5xx → reintentar (hasta maxRetries, backoff exponencial)
│
├─ json_decode($body, true)
│   └─ $data['data'] → array de incidentes crudos
│
├─ Mapear cada item → new Incident(array)
│   id              ← $item['id']
│   title           ← $item['title']
│   description     ← $item['description']
│   applicationId   ← $item['applicationId']
│   applicationName ← $item['applicationName'] ?? null
│   severityId      ← (int) $item['severityId']
│   severityName    ← $item['severityName'] ?? null
│   statusName      ← $item['statusName'] ?? null
│   assignedToId    ← $item['assignedToId'] ?? null
│   assignedToName  ← $item['assignedToName'] ?? null
│   createdAt       ← new DateTimeImmutable($item['createdAt'])
│
└─ Retornar:
    [
      'incidents'  => Incident[],
      'total'      => $data['total'],
      'page'       => $data['page'],
      'limit'      => $data['limit'],
      'totalPages' => $data['totalPages'],
    ]
```

### Estrategia de reintentos

```php
$maxRetries = 3;     // configurable con setMaxRetries()
$attempt    = 0;

while ($attempt < $maxRetries) {
    $response = curlRequest(...);

    if ($response->httpCode < 400) break;          // éxito
    if ($response->httpCode < 500) throw ...;      // 4xx: no reintentar

    $attempt++;
    if ($attempt < $maxRetries) {
        sleep(2 ** $attempt);                      // 2s → 4s → 8s
    }
}
```

---

## Mapeo de datos: API JSON → PHP

| Campo JSON         | Tipo JSON    | Getter PHP                | Tipo PHP             |
|--------------------|--------------|---------------------------|----------------------|
| `id`               | string       | `getId()`                 | `string`             |
| `title`            | string       | `getTitle()`              | `string`             |
| `description`      | string       | `getDescription()`        | `string`             |
| `applicationId`    | string       | `getApplicationId()`      | `string`             |
| `applicationName`  | string\|null | `getApplicationName()`    | `?string`            |
| `severityId`       | number       | `getSeverityId()`         | `int`                |
| `severityName`     | string\|null | `getSeverityName()`       | `?string`            |
| `statusName`       | string\|null | `getStatusName()`         | `?string` (= "OPEN") |
| `assignedToId`     | string\|null | `getAssignedToId()`       | `?string`            |
| `assignedToName`   | string\|null | `getAssignedToName()`     | `?string`            |
| `createdAt`        | ISO 8601     | `getCreatedAt()`          | `DateTimeImmutable`  |

> `severityColor` está disponible en el JSON pero no se mapea al modelo PHP — el dashboard legacy usa clases CSS propias basadas en el nombre de severidad.

---

## Configuración

El cliente se configura con variables de entorno (`.env`) o por inyección directa:

```php
// Por variables de entorno (.env)
INCIDENT_API_URL=http://localhost:3001
INCIDENT_API_TIMEOUT=10
INCIDENT_VERIFY_SSL=true

// Por constructor (sobreescribe env vars)
$client = new IncidentClient([
    'base_url'   => 'https://api.coordinadora.com',
    'timeout'    => 30,
    'verify_ssl' => false,    // en desarrollo con certificados autofirmados
]);
```

> **Importante:** `INCIDENT_API_URL` apunta a la raíz del backend (ej. `http://localhost:3001`), no incluye `/api`. El cliente antepone `/api/incidents/open` internamente.

---

## Arquitectura de la solución moderna

La API que consume el legacy sigue arquitectura hexagonal (DDD):

```
Presentation  →  Application  →  Domain  →  Infrastructure
────────────     ───────────     ──────     ──────────────
Controller        UseCase        Port       PrismaRepository
(HTTP/DTO)       (orquesta)    (interfaz)   (Prisma + PG)
```

**Módulo de incidentes relevante:**

```
backend/src/features/incidents/
├── presentation/
│   ├── incidents.controller.ts                    # GET /incidents/open
│   └── dtos/
│       └── list-open-incidents-filter.dto.ts      # applicationId?, severityId?
├── application/
│   └── use-cases/
│       └── list-open-incidents.use-case.ts
├── domain/
│   └── ports/
│       └── incident-repository.port.ts            # findAllOpen()
└── infrastructure/
    └── repositories/
        └── incident-prisma.repository.ts          # WHERE currentStatusId = 1
```

---

## Agregar un nuevo campo al contrato

Si el backend expone un nuevo campo y se quiere consumir en el legacy:

1. Verificar que el campo aparece en la respuesta JSON (Swagger disponible en `/api/docs`).
2. **`src/Models/Incident.php`** — agregar propiedad privada y getter.
3. **`src/IncidentClient.php`** — mapear `$item['nuevoCampo'] ?? null` en el constructor del modelo.
4. **`examples/legacy_dashboard.php`** — usar el getter en la tabla HTML si aplica.
5. **`tests/IncidentTest.php`** — agregar el campo al array de datos de prueba.

---

## Troubleshooting

### "Connection refused" o "Could not connect"

- Backend NestJS no está corriendo
- `INCIDENT_API_URL` apunta a host/puerto incorrecto

```bash
# Verificar que el backend responde
curl http://localhost:3001/api/incidents/open?limit=1
```

### "HTTP 404"

- Verificar que `INCIDENT_API_URL` no incluye `/api` al final (el cliente lo agrega)
- Correcto: `http://localhost:3001`
- Incorrecto: `http://localhost:3001/api`

### "Timeout"

- Aumentar `INCIDENT_API_TIMEOUT` en `.env` (default: 10 segundos)
- Verificar latencia de red entre el servidor PHP y el backend

### "JSON decode error"

- El backend devolvió una respuesta no-JSON (posible error de proxy o firewall)
- Activar `LOG_LEVEL=debug` para ver la respuesta cruda en el log

### Logs

```bash
# Ver logs en tiempo real
tail -f /var/log/legacy-integration.log   # o la ruta en LOG_PATH

# Formato de cada entrada:
[2026-06-26 14:35:00] INFO: GET /api/incidents/open?page=1&limit=20 - 200 - 145ms
[2026-06-26 14:35:05] WARNING: Retry 1/3 - Status 503
[2026-06-26 14:35:07] ERROR: Max retries reached - Connection timeout
```
