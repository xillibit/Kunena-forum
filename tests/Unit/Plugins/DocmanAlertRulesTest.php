<?php

namespace Kunena\Tests\Unit\Plugins;

use Kunena\Plugin\Task\DocmanAlerts\AlertRules;
use PHPUnit\Framework\TestCase;

class DocmanAlertRulesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!\defined('_JEXEC')) {
            \define('_JEXEC', 1);
        }

        require_once __DIR__ . '/../../../src/plugins/plg_task_docmanalerts/src/AlertRules.php';
    }

    public function testThresholdsAndCatchUpBeforeExpiration(): void
    {
        $now = new \DateTimeImmutable('2026-01-01 12:00:00', new \DateTimeZone('UTC'));

        self::assertTrue(AlertRules::isDue('2026-04-01 23:59:59', $now, 90));
        self::assertFalse(AlertRules::isDue('2026-04-02 00:00:00', $now, 90));
        self::assertTrue(AlertRules::isDue('2026-01-31 23:59:59', $now, 30));
        self::assertFalse(AlertRules::isDue('2026-02-01 00:00:00', $now, 30));
        self::assertTrue(AlertRules::isDue('2026-01-02 00:00:00', $now, 90));
        self::assertFalse(AlertRules::isDue('2026-01-01 12:00:00', $now, 30));
        self::assertFalse(AlertRules::isDue('0000-00-00 00:00:00', $now, 90));
    }

    public function testEncodesNetworkPathAsFileUri(): void
    {
        self::assertSame(
            'file://server/share/Convention%20%C3%A9%23.pdf',
            AlertRules::networkFileUri('\\\\server\\share\\Convention é#.pdf')
        );
    }

    public function testRejectsUnsafeNetworkPath(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AlertRules::networkFileUri('\\\\server\\share\\..\\secret.pdf');
    }
}
