<?php

/**
 * @package    Kunena.Plugin
 * @license    GNU General Public License version 3 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;

return new class () implements InstallerScriptInterface {
    public function install(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function update(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function uninstall(InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        return true;
    }

    public function preflight(string $type, InstallerAdapter $adapter): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $table = $db->replacePrefix('#__docman_documents');

        if (!\in_array($table, $db->getTableList(), true)) {
            throw new \RuntimeException('DOCman must be installed before the DOCman alerts plugin.');
        }

        $columns = $db->getTableColumns($table);

        foreach (['alert_90_sent', 'alert_30_sent'] as $column) {
            if (!isset($columns[$column])) {
                $db->setQuery(
                    'ALTER TABLE ' . $db->quoteName('#__docman_documents')
                    . ' ADD COLUMN ' . $db->quoteName($column) . ' DATETIME NULL'
                )->execute();
            }
        }

        $db->setQuery(
            'CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__docman_alert_deliveries') . ' ('
            . $db->quoteName('document_id') . ' BIGINT UNSIGNED NOT NULL, '
            . $db->quoteName('milestone') . ' SMALLINT UNSIGNED NOT NULL, '
            . $db->quoteName('user_id') . ' BIGINT UNSIGNED NOT NULL, '
            . $db->quoteName('sent_at') . ' DATETIME NOT NULL, '
            . 'PRIMARY KEY (' . $db->quoteName('document_id') . ', '
            . $db->quoteName('milestone') . ', ' . $db->quoteName('user_id') . ')'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        )->execute();

        return true;
    }
};
