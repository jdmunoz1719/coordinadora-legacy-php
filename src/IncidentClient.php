<?php

declare(strict_types=1);

namespace Coordinadora\Legacy;

use Coordinadora\Legacy\Exceptions\IncidentClientException;
use Coordinadora\Legacy\Models\Incident;
use Coordinadora\Legacy\Loggers\Logger;

/**
 * Cliente HTTP para consumir incidentes desde la plataforma moderna.
 *
 * Usa cURL para máxima compatibilidad con sistemas legacy.
 * Thread-safe. Maneja reintentos y timeout automáticamente.
 */
class IncidentClient
{
    private Config $config;
    private Logger $logger;
    private int $maxRetries = 3;
    private int $retryDelayMs = 500;

    public function __construct(array $configOverrides = [])
    {
        $this->config = new Config($configOverrides);
        $this->config->ensureLogDirectory();

        $this->logger = new Logger(
            $this->config->getLogPath(),
            $this->config->getLogLevel()
        );

        $this->logger->info('IncidentClient initialized', [
            'api_url' => $this->config->getBaseUrl(),
            'timeout' => $this->config->getTimeout(),
        ]);
    }

    /**
     * Retorna lista paginada de incidentes abiertos (status = OPEN).
     *
     * @param array $filters = [
     *     'page'          => 1,
     *     'limit'         => 20,
     *     'applicationId' => 'optional-uuid',
     *     'severityId'    => 2,
     * ]
     * @return array{ incidents: Incident[], total: int, page: int, limit: int }
     * @throws IncidentClientException
     */
    public function getOpenIncidents(array $filters = []): array
    {
        $page = (int) ($filters['page'] ?? 1);
        $limit = min((int) ($filters['limit'] ?? 20), 100);

        $queryParams = ['page' => $page, 'limit' => $limit];

        if (!empty($filters['applicationId'])) {
            $queryParams['applicationId'] = $filters['applicationId'];
        }

        if (!empty($filters['severityId'])) {
            $queryParams['severityId'] = (int) $filters['severityId'];
        }

        $url = $this->config->getBaseUrl() . '/incidents/open?' . http_build_query($queryParams);

        $this->logger->debug('Fetching open incidents', ['url' => $url, 'page' => $page]);

        try {
            $response = $this->request('GET', $url);
            $data = json_decode($response, true);

            if (!is_array($data) || !isset($data['data'])) {
                throw new IncidentClientException('Invalid API response format');
            }

            $incidents = array_map(
                fn($item) => new Incident($item),
                $data['data']
            );

            $this->logger->info('Fetched open incidents', [
                'count' => count($incidents),
                'total' => $data['total'] ?? 0,
                'page' => $page,
            ]);

            return [
                'incidents'  => $incidents,
                'total'      => $data['total'] ?? 0,
                'page'       => $data['page'] ?? $page,
                'limit'      => $data['limit'] ?? $limit,
                'totalPages' => $data['totalPages'] ?? 1,
            ];
        } catch (IncidentClientException $e) {
            $this->logger->error('Failed to fetch open incidents', [
                'error'     => $e->getMessage(),
                'http_code' => $e->getHttpCode(),
            ]);
            throw $e;
        }
    }

    /**
     * Configura el nivel de logging.
     */
    public function setLogLevel(string $level): void
    {
        $this->logger->setMinLevel($level);
    }

    /**
     * Configura el número máximo de reintentos para fallos transitorios.
     */
    public function setMaxRetries(int $maxRetries): void
    {
        $this->maxRetries = max(0, $maxRetries);
    }

    /**
     * Configura el delay entre reintentos en milisegundos.
     */
    public function setRetryDelayMs(int $ms): void
    {
        $this->retryDelayMs = max(0, $ms);
    }

    /**
     * Realiza una request HTTP con reintentos automáticos.
     *
     * @throws IncidentClientException
     */
    private function request(string $method, string $url): string
    {
        $attempt = 0;

        while ($attempt <= $this->maxRetries) {
            try {
                return $this->executeCurl($method, $url);
            } catch (IncidentClientException $e) {
                $attempt++;

                // No reintentar en errores de validación/autenticación
                if ($e->getHttpCode() && in_array($e->getHttpCode(), [400, 401, 403, 404], true)) {
                    throw $e;
                }

                if ($attempt > $this->maxRetries) {
                    $this->logger->error('Max retries exceeded', [
                        'url' => $url,
                        'attempts' => $attempt,
                        'last_error' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                $delay = $this->retryDelayMs * $attempt;
                $this->logger->warning('Request failed, retrying', [
                    'url' => $url,
                    'attempt' => $attempt,
                    'delay_ms' => $delay,
                    'error' => $e->getMessage(),
                ]);

                usleep($delay * 1000);
            }
        }

        throw new IncidentClientException('Unexpected state in request retry loop');
    }

    /**
     * Ejecuta la request cURL.
     *
     * @throws IncidentClientException
     */
    protected function executeCurl(string $method, string $url): string
    {
        $ch = curl_init();

        try {
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->config->getTimeout(),
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'User-Agent: Coordinadora-Legacy-Client/1.0',
                    'Accept: application/json',
                ],
                CURLOPT_SSL_VERIFYPEER => $this->config->isVerifySsl(),
                CURLOPT_SSL_VERIFYHOST => $this->config->isVerifySsl() ? 2 : 0,
            ]);

            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlErrno = curl_errno($ch);

            // Manejo de errores cURL
            if ($curlErrno !== 0) {
                if ($curlErrno === CURLE_OPERATION_TIMEDOUT) {
                    throw IncidentClientException::timeout($url, $this->config->getTimeout());
                }
                throw IncidentClientException::connectionError($url, $curlError);
            }

            // Manejo de errores HTTP
            if ($httpCode >= 400) {
                throw IncidentClientException::fromHttpError($httpCode, (string) $response);
            }

            return (string) $response;
        } finally {
            unset($ch);
        }
    }
}
