<?php

declare(strict_types=1);

namespace Coordinadora\Legacy\Tests;

use Coordinadora\Legacy\Loggers\Logger;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = sys_get_temp_dir() . '/legacy-logger-test-' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    private function readLog(): string
    {
        return file_exists($this->logFile) ? file_get_contents($this->logFile) : '';
    }

    // ── File creation ─────────────────────────────────────────────────────────

    public function testCreatesLogFileOnInstantiation(): void
    {
        new Logger($this->logFile);
        $this->assertFileExists($this->logFile);
    }

    // ── Level filtering ───────────────────────────────────────────────────────

    public function testDebugSkippedAtInfoLevel(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $logger->debug('should not appear');
        $this->assertStringNotContainsString('should not appear', $this->readLog());
    }

    public function testInfoWrittenAtInfoLevel(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $logger->info('info message');
        $this->assertStringContainsString('info message', $this->readLog());
    }

    public function testDebugSkippedAtWarningLevel(): void
    {
        $logger = new Logger($this->logFile, 'warning');
        $logger->info('info skipped');
        $this->assertStringNotContainsString('info skipped', $this->readLog());
    }

    public function testWarningWrittenAtWarningLevel(): void
    {
        $logger = new Logger($this->logFile, 'warning');
        $logger->warning('watch out');
        $this->assertStringContainsString('watch out', $this->readLog());
    }

    public function testErrorAlwaysWritten(): void
    {
        $logger = new Logger($this->logFile, 'error');
        $logger->error('critical failure');
        $this->assertStringContainsString('critical failure', $this->readLog());
    }

    public function testDebugWrittenAtDebugLevel(): void
    {
        $logger = new Logger($this->logFile, 'debug');
        $logger->debug('debug detail');
        $this->assertStringContainsString('debug detail', $this->readLog());
    }

    // ── Log format ────────────────────────────────────────────────────────────

    public function testLogLineContainsLevelTag(): void
    {
        $logger = new Logger($this->logFile, 'debug');
        $logger->warning('test warning');
        $this->assertStringContainsString('[warning]', $this->readLog());
    }

    public function testLogLineContainsTimestamp(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $logger->info('with timestamp');
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $this->readLog());
    }

    public function testContextIsJsonEncodedInLog(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $logger->info('event', ['key' => 'value', 'count' => 3]);
        $this->assertStringContainsString('"key":"value"', $this->readLog());
    }

    public function testNoContextSeparatorWhenContextEmpty(): void
    {
        $logger = new Logger($this->logFile, 'info');
        $logger->info('clean message');
        $this->assertStringNotContainsString('| ', $this->readLog());
    }

    // ── setMinLevel ───────────────────────────────────────────────────────────

    public function testSetMinLevelRestrictsOutput(): void
    {
        $logger = new Logger($this->logFile, 'debug');
        $logger->debug('debug before change');

        $logger->setMinLevel('error');
        $logger->warning('warning after change');
        $logger->error('error after change');

        $content = $this->readLog();
        $this->assertStringContainsString('debug before change', $content);
        $this->assertStringNotContainsString('warning after change', $content);
        $this->assertStringContainsString('error after change', $content);
    }

    public function testSetMinLevelAllowsLowerLevelAfterUpgrade(): void
    {
        $logger = new Logger($this->logFile, 'error');
        $logger->setMinLevel('debug');
        $logger->debug('now visible');
        $this->assertStringContainsString('now visible', $this->readLog());
    }
}
