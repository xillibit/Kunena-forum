<?php

/**
 * @package    Kunena.Plugin
 * @license    GNU General Public License version 3 or later
 */

namespace Kunena\Plugin\Task\DocmanAlerts;

\defined('_JEXEC') or die;

final class AlertRules
{
    public static function isDue(string $expiration, \DateTimeImmutable $now, int $days): bool
    {
        $deadline = $now->setTime(0, 0)->modify('+' . ($days + 1) . ' days');

        return $expiration > $now->format('Y-m-d H:i:s') && $expiration < $deadline->format('Y-m-d H:i:s');
    }

    public static function networkFileUri(string $path): string
    {
        if (!str_starts_with($path, '\\\\')) {
            throw new \InvalidArgumentException('Expected a UNC network path.');
        }

        $parts = explode('\\', substr($path, 2));
        $server = array_shift($parts);

        if (
            !preg_match('/^[a-zA-Z0-9._-]+$/D', $server)
            || \count($parts) < 2
            || \in_array('', $parts, true)
            || \in_array('.', $parts, true)
            || \in_array('..', $parts, true)
        ) {
            throw new \InvalidArgumentException('Invalid UNC network path.');
        }

        return 'file://' . $server . '/' . implode('/', array_map('rawurlencode', $parts));
    }
}
