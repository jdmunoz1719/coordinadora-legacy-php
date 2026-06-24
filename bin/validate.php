#!/usr/bin/env php
<?php

/**
 * Script de validación: verifica que el componente legacy está correctamente configurado.
 *
 * Uso:
 *   php bin/validate.php
 */

declare(strict_types=1);

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║   Coordinadora Legacy Integration - Validation Script          ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$errors = [];
$warnings = [];

// 1. Verificar PHP version
echo "1️⃣  Checking PHP version... ";
if (version_compare(PHP_VERSION, '8.0', '>=')) {
    echo "✅ PHP " . PHP_VERSION . "\n";
} else {
    $errors[] = "PHP 8.0+ required (current: " . PHP_VERSION . ")";
    echo "❌\n";
}

// 2. Verificar extensión cURL
echo "2️⃣  Checking cURL extension... ";
if (extension_loaded('curl')) {
    echo "✅\n";
} else {
    $errors[] = "cURL extension not loaded";
    echo "❌\n";
}

// 3. Verificar archivos principales
echo "3️⃣  Checking main files... ";
$mainFiles = [
    'src/IncidentClient.php',
    'src/Config.php',
    'src/Models/Incident.php',
    'src/Exceptions/IncidentClientException.php',
];
$allExist = true;
foreach ($mainFiles as $file) {
    if (!file_exists(__DIR__ . '/../' . $file)) {
        $errors[] = "Missing file: $file";
        $allExist = false;
    }
}
echo $allExist ? "✅\n" : "❌\n";

// 4. Verificar autoloader
echo "4️⃣  Checking autoloader... ";
$autoloadFile = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadFile)) {
    echo "✅ Composer installed\n";
    require_once $autoloadFile;
} else {
    echo "⚠️  composer not installed yet\n";
    $warnings[] = "Run 'composer install' to set up dependencies";
}

// 5. Cargar clases si está disponible
echo "5️⃣  Testing class loading... ";
if (file_exists($autoloadFile)) {
    try {
        $client = new \Coordinadora\Legacy\IncidentClient();
        echo "✅\n";
    } catch (Exception $e) {
        $errors[] = "Failed to instantiate IncidentClient: " . $e->getMessage();
        echo "❌\n";
    }
} else {
    echo "⏭️  Skipped (run composer install first)\n";
}

// 6. Verificar .env.example
echo "6️⃣  Checking .env.example... ";
if (file_exists(__DIR__ . '/../.env.example')) {
    echo "✅\n";
} else {
    $warnings[] = "Missing .env.example template";
    echo "⚠️\n";
}

// 7. Verificar configuración de logs
echo "7️⃣  Checking log directory... ";
$logDir = dirname($_ENV['LOG_PATH'] ?? sys_get_temp_dir() . '/legacy-integration.log');
if (is_writable($logDir)) {
    echo "✅ $logDir\n";
} else {
    if (is_dir($logDir)) {
        $warnings[] = "Log directory exists but not writable: $logDir";
    } else {
        $warnings[] = "Log directory does not exist: $logDir";
    }
    echo "⚠️\n";
}

// 8. Verificar documentación
echo "8️⃣  Checking documentation... ";
$docs = [
    'README.md',
    'docs/INTEGRATION.md',
    'STRUCTURE.md',
];
$allDocs = true;
foreach ($docs as $doc) {
    if (!file_exists(__DIR__ . '/../' . $doc)) {
        $warnings[] = "Missing documentation: $doc";
        $allDocs = false;
    }
}
if ($allDocs) {
    echo "✅\n";
} else {
    echo "⚠️\n";
}

// 9. Verificar ejemplos
echo "9️⃣  Checking examples... ";
$examples = [
    'examples/fetch_incidents.php',
    'examples/legacy_dashboard.php',
];
$allExamples = true;
foreach ($examples as $example) {
    if (!file_exists(__DIR__ . '/../' . $example)) {
        $warnings[] = "Missing example: $example";
        $allExamples = false;
    }
}
echo $allExamples ? "✅\n" : "⚠️\n";

// 10. Verificar tests
echo "🔟 Checking tests... ";
if (file_exists(__DIR__ . '/../tests/IncidentTest.php')) {
    echo "✅\n";
} else {
    $warnings[] = "Missing tests/IncidentTest.php";
    echo "⚠️\n";
}

echo "\n" . str_repeat("═", 66) . "\n";

// Resumen
if (!empty($errors)) {
    echo "❌ ERRORS (" . count($errors) . "):\n";
    foreach ($errors as $error) {
        echo "   • $error\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  WARNINGS (" . count($warnings) . "):\n";
    foreach ($warnings as $warning) {
        echo "   • $warning\n";
    }
    echo "\n";
}

if (empty($errors) && empty($warnings)) {
    echo "✅ All checks passed! Your legacy integration is ready.\n\n";
    echo "Next steps:\n";
    echo "   1. Set up .env from .env.example\n";
    echo "   2. Run: php examples/fetch_incidents.php 1 10\n";
    echo "   3. Read docs/INTEGRATION.md for architecture details\n";
    exit(0);
} elseif (empty($errors)) {
    echo "✅ Ready to use (with minor warnings)\n";
    exit(0);
} else {
    echo "❌ Fix errors above before using\n";
    exit(1);
}
