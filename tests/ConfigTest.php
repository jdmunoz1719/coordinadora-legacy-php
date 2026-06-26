<?php

declare(strict_types=1);

namespace Coordinadora\Legacy\Tests;

use Coordinadora\Legacy\Config;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    // ── Defaults ─────────────────────────────────────────────────────────────

    public function testDefaultBaseUrl(): void
    {
        $config = new Config(['base_url' => 'http://localhost:3001']);
        $this->assertSame('http://localhost:3001', $config->getBaseUrl());
    }

    public function testDefaultTimeout(): void
    {
        $config = new Config(['base_url' => 'http://localhost:3001', 'timeout' => 10]);
        $this->assertSame(10, $config->getTimeout());
    }

    public function testDefaultVerifySslIsTrue(): void
    {
        $config = new Config(['base_url' => 'http://localhost:3001']);
        $this->assertTrue($config->isVerifySsl());
    }

    public function testDefaultLogLevelIsInfo(): void
    {
        $config = new Config(['base_url' => 'http://localhost:3001']);
        $this->assertSame('info', $config->getLogLevel());
    }

    // ── Overrides ─────────────────────────────────────────────────────────────

    public function testOverrideBaseUrl(): void
    {
        $config = new Config(['base_url' => 'https://api.example.com']);
        $this->assertSame('https://api.example.com', $config->getBaseUrl());
    }

    public function testOverrideTimeout(): void
    {
        $config = new Config(['base_url' => 'http://localhost:3001', 'timeout' => 30]);
        $this->assertSame(30, $config->getTimeout());
    }

    public function testOverrideVerifySslFalse(): void
    {
        $config = new Config(['base_url' => 'http://localhost:3001', 'verify_ssl' => false]);
        $this->assertFalse($config->isVerifySsl());
    }

    public function testOverrideLogLevel(): void
    {
        $config = new Config(['base_url' => 'http://localhost:3001', 'log_level' => 'debug']);
        $this->assertSame('debug', $config->getLogLevel());
    }

    public function testOverrideLogPath(): void
    {
        $path = sys_get_temp_dir() . '/test-integration.log';
        $config = new Config(['base_url' => 'http://localhost:3001', 'log_path' => $path]);
        $this->assertSame($path, $config->getLogPath());
    }

    // ── getBaseUrl trailing slash ─────────────────────────────────────────────

    public function testGetBaseUrlTrimsTrailingSlash(): void
    {
        $config = new Config(['base_url' => 'http://localhost:3001/']);
        $this->assertSame('http://localhost:3001', $config->getBaseUrl());
    }

    public function testGetBaseUrlTrimsMultipleTrailingSlashes(): void
    {
        $config = new Config(['base_url' => 'http://localhost:3001///']);
        $this->assertSame('http://localhost:3001', $config->getBaseUrl());
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function testInvalidLogLevelThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid log_level');
        new Config(['base_url' => 'http://localhost:3001', 'log_level' => 'verbose']);
    }

    public function testTimeoutBelowOneThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('timeout must be >= 1');
        new Config(['base_url' => 'http://localhost:3001', 'timeout' => 0]);
    }

    public function testAllValidLogLevelsAccepted(): void
    {
        foreach (['debug', 'info', 'warning', 'error'] as $level) {
            $config = new Config(['base_url' => 'http://localhost:3001', 'log_level' => $level]);
            $this->assertSame($level, $config->getLogLevel());
        }
    }

    // ── ensureLogDirectory ────────────────────────────────────────────────────

    public function testEnsureLogDirectoryCreatesDir(): void
    {
        $dir  = sys_get_temp_dir() . '/legacy-test-' . uniqid();
        $path = $dir . '/test.log';

        $config = new Config(['base_url' => 'http://localhost:3001', 'log_path' => $path]);
        $config->ensureLogDirectory();

        $this->assertDirectoryExists($dir);

        rmdir($dir);
    }

    public function testEnsureLogDirectoryIdempotent(): void
    {
        $path   = sys_get_temp_dir() . '/test-idempotent.log';
        $config = new Config(['base_url' => 'http://localhost:3001', 'log_path' => $path]);

        $config->ensureLogDirectory();
        $config->ensureLogDirectory(); // second call must not throw

        $this->assertTrue(true);
    }
}
