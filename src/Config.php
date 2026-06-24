<?php

declare(strict_types=1);

namespace Coordinadora\Legacy;

/**
 * Gestión de configuración del cliente.
 * Carga desde env vars y permite sobrescribir programáticamente.
 */
class Config
{
    private string $baseUrl;
    private int $timeout;
    private bool $verifySsl;
    private string $logLevel;
    private string $logPath;

    public function __construct(array $overrides = [])
    {
        $this->baseUrl = $overrides['base_url'] ?? $_ENV['INCIDENT_API_URL'] ?? 'http://localhost:3001';
        $this->timeout = (int)($overrides['timeout'] ?? $_ENV['INCIDENT_API_TIMEOUT'] ?? 10);
        $this->verifySsl = (bool)($overrides['verify_ssl'] ?? ($_ENV['INCIDENT_VERIFY_SSL'] ?? true));
        $this->logLevel = $overrides['log_level'] ?? $_ENV['LOG_LEVEL'] ?? 'info';
        $this->logPath = $overrides['log_path'] ?? $_ENV['LOG_PATH'] ?? sys_get_temp_dir() . '/legacy-integration.log';

        $this->validate();
    }

    private function validate(): void
    {
        if (empty($this->baseUrl)) {
            throw new \InvalidArgumentException('base_url cannot be empty');
        }

        if ($this->timeout < 1) {
            throw new \InvalidArgumentException('timeout must be >= 1');
        }

        if (!in_array($this->logLevel, ['debug', 'info', 'warning', 'error'], true)) {
            throw new \InvalidArgumentException("Invalid log_level: {$this->logLevel}");
        }
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function isVerifySsl(): bool
    {
        return $this->verifySsl;
    }

    public function getLogLevel(): string
    {
        return $this->logLevel;
    }

    public function getLogPath(): string
    {
        return $this->logPath;
    }

    /**
     * Asegura que el directorio de logs existe.
     */
    public function ensureLogDirectory(): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
