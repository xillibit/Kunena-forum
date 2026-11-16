<?php

namespace Kunena\Tests\Unit\Plugins;

use PHPUnit\Framework\TestCase;

class DocmanAlertsIntegrationTest extends TestCase
{
    public function testDeliveryAndRetry(): void
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/fixtures/docman-alerts.php');
        exec($command . ' 2>&1', $output, $status);

        self::assertSame(0, $status, implode("\n", $output));
        self::assertStringContainsString('DOCman alert integration checks passed', implode("\n", $output));
    }
}
