<?php

declare(strict_types=1);

namespace Coordinadora\Legacy\Exceptions;

use Exception;

/**
 * Excepción para errores del cliente de incidentes.
 */
class IncidentClientException extends Exception
{
    private ?int $httpCode = null;
    private ?string $responseBody = null;

    public function __construct(
        string $message,
        int $code = 0,
        ?int $httpCode = null,
        ?string $responseBody = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->httpCode = $httpCode;
        $this->responseBody = $responseBody;
    }

    public function getHttpCode(): ?int
    {
        return $this->httpCode;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    /**
     * Crea excepción desde respuesta HTTP fallida.
     */
    public static function fromHttpError(int $httpCode, string $responseBody): self
    {
        $message = match ($httpCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            408 => 'Request Timeout',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            default => "HTTP $httpCode",
        };

        return new self(
            "API error: $message",
            $httpCode,
            $httpCode,
            $responseBody
        );
    }

    /**
     * Crea excepción para timeout.
     */
    public static function timeout(string $url, int $timeout): self
    {
        return new self(
            "Request to $url timed out after {$timeout}s",
            CURLE_OPERATION_TIMEDOUT
        );
    }

    /**
     * Crea excepción para errores de conexión.
     */
    public static function connectionError(string $url, string $curlError): self
    {
        return new self("Connection error to $url: $curlError", CURLE_COULDNT_RESOLVE_HOST);
    }
}
