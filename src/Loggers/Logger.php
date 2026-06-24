<?php

declare(strict_types=1);

namespace Coordinadora\Legacy\Loggers;

/**
 * Logger simple para legacy integration.
 * Implementa básicamente PSR-3 (sin todo el boilerplate).
 */
class Logger
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    private string $logFile;
    private int $minLevel;

    public function __construct(string $logFile, string $minLevel = 'info')
    {
        $this->logFile = $logFile;
        $this->minLevel = self::LEVELS[$minLevel] ?? self::LEVELS['info'];

        $this->ensureFile();
    }

    private function ensureFile(): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if (self::LEVELS[$level] < $this->minLevel) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' | ' . json_encode($context);
        $line = "[$timestamp] [$level] $message$contextStr\n";

        error_log($line, 3, $this->logFile);
    }

    public function setMinLevel(string $level): void
    {
        $this->minLevel = self::LEVELS[$level] ?? self::LEVELS['info'];
    }
}
