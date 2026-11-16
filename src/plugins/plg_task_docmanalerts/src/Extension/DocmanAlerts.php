<?php

/**
 * @package    Kunena.Plugin
 * @license    GNU General Public License version 3 or later
 */

namespace Kunena\Plugin\Task\DocmanAlerts\Extension;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Kunena\Plugin\Task\DocmanAlerts\AlertRules;

\defined('_JEXEC') or die;

final class DocmanAlerts extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    private const TASKS_MAP = [
        'docman.expiration_alerts' => [
            'langConstPrefix' => 'PLG_TASK_DOCMANALERTS_ROUTINE',
            'method'          => 'sendAlerts',
        ],
    ];

    protected $autoloadLanguage = true;

    public function __construct(array $config, private MailerFactoryInterface $mailerFactory)
    {
        parent::__construct($config);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    private function sendAlerts(ExecuteTaskEvent $event): int
    {
        $db = $this->getDatabase();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $cutoff = $now->setTime(0, 0)->modify('+91 days')->format('Y-m-d H:i:s');
        $failed = false;

        try {
            $query = $db->createQuery()
                ->select($db->quoteName('docman_document_id'))
                ->from($db->quoteName('#__docman_documents'))
                ->where($db->quoteName('unpublish_on') . ' > ' . $db->quote($now->format('Y-m-d H:i:s')))
                ->where($db->quoteName('unpublish_on') . ' < ' . $db->quote($cutoff))
                ->where('(' . $db->quoteName('alert_90_sent') . ' IS NULL OR ' . $db->quoteName('alert_30_sent') . ' IS NULL)');

            $db->setQuery($query);
            $documentIds = $db->loadColumn();

            foreach ($documentIds as $documentId) {
                foreach ([90, 30] as $days) {
                    if (!$this->processAlert((int) $documentId, $days, $now)) {
                        $failed = true;
                    }
                }
            }
        } catch (\Throwable $e) {
            $this->logTask(Text::sprintf('PLG_TASK_DOCMANALERTS_ERROR', $e->getMessage()));

            return Status::KNOCKOUT;
        }

        return $failed ? Status::KNOCKOUT : Status::OK;
    }

    private function processAlert(int $documentId, int $days, \DateTimeImmutable $now): bool
    {
        $db = $this->getDatabase();
        $column = 'alert_' . $days . '_sent';
        $db->transactionStart();

        try {
            $query = $db->createQuery()
                ->select('*')
                ->from($db->quoteName('#__docman_documents'))
                ->where($db->quoteName('docman_document_id') . ' = ' . $documentId);
            $db->setQuery((string) $query . ' FOR UPDATE');
            $document = $db->loadObject();

            if (!$document || $document->$column !== null || !AlertRules::isDue($document->unpublish_on, $now, $days)) {
                $db->transactionCommit();

                return true;
            }

            $uri = AlertRules::networkFileUri($document->storage_path);
            $recipients = $this->getRecipients((int) $document->docman_category_id);

            if (!$recipients) {
                throw new \RuntimeException('No active recipients or DOCman group level for category ' . (int) $document->docman_category_id);
            }

            $failed = false;

            foreach ($recipients as $recipient) {
                if ($this->wasDelivered($documentId, $days, (int) $recipient->id)) {
                    continue;
                }

                try {
                    $mailer = $this->mailerFactory->createMailer();

                    if (!$mailer instanceof Mail) {
                        throw new \RuntimeException('The Joomla mailer does not support HTML messages.');
                    }

                    $mailer->isHtml(true);
                    $mailer->addRecipient($recipient->email, $recipient->name);
                    $mailer->setSubject(Text::sprintf('PLG_TASK_DOCMANALERTS_MAIL_SUBJECT', $days, $document->title));
                    $mailer->setBody(Text::sprintf(
                        'PLG_TASK_DOCMANALERTS_MAIL_BODY',
                        htmlspecialchars($document->title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                        htmlspecialchars($document->unpublish_on, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                        $days,
                        htmlspecialchars($uri, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                        htmlspecialchars($document->storage_path, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    ));

                    if ($mailer->send() === false) {
                        throw new \RuntimeException('The mailer returned failure.');
                    }
                } catch (\Throwable $e) {
                    $failed = true;
                    $this->logTask(Text::sprintf('PLG_TASK_DOCMANALERTS_SEND_ERROR', $documentId, $days, $recipient->id, $e->getMessage()));
                    continue;
                }

                $query = $db->createQuery()
                    ->insert($db->quoteName('#__docman_alert_deliveries'))
                    ->columns($db->quoteName(['document_id', 'milestone', 'user_id', 'sent_at']))
                    ->values($documentId . ', ' . $days . ', ' . (int) $recipient->id . ', ' . $db->quote(gmdate('Y-m-d H:i:s')));
                $db->setQuery($query)->execute();
                $this->logTask(Text::sprintf('PLG_TASK_DOCMANALERTS_SENT', $documentId, $days, $recipient->id));
            }

            if (!$failed) {
                $query = $db->createQuery()
                    ->update($db->quoteName('#__docman_documents'))
                    ->set($db->quoteName($column) . ' = ' . $db->quote(gmdate('Y-m-d H:i:s')))
                    ->where($db->quoteName('docman_document_id') . ' = ' . $documentId);
                $db->setQuery($query)->execute();
            }

            $db->transactionCommit();

            return !$failed;
        } catch (\Throwable $e) {
            $db->transactionRollback();
            $this->logTask(Text::sprintf('PLG_TASK_DOCMANALERTS_DOCUMENT_ERROR', $documentId, $days, $e->getMessage()));

            return false;
        }
    }

    private function getRecipients(int $categoryId): array
    {
        $db = $this->getDatabase();
        $query = $db->createQuery()
            ->select('DISTINCT ' . implode(', ', $db->quoteName(['u.id', 'u.name', 'u.email'])))
            ->from($db->quoteName('#__docman_categories', 'c'))
            ->innerJoin($db->quoteName('#__docman_levels', 'l') . ' ON ' . $db->quoteName('l.docman_level_id') . ' = -' . $db->quoteName('c.access'))
            ->innerJoin($db->quoteName('#__user_usergroup_map', 'm') . ' ON FIND_IN_SET(' . $db->quoteName('m.group_id') . ', ' . $db->quoteName('l.groups') . ') > 0')
            ->innerJoin($db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('m.user_id'))
            ->where($db->quoteName('c.docman_category_id') . ' = ' . $categoryId)
            ->where($db->quoteName('c.access') . ' < 0')
            ->where($db->quoteName('u.block') . ' = 0');
        $db->setQuery($query);

        return $db->loadObjectList();
    }

    private function wasDelivered(int $documentId, int $days, int $userId): bool
    {
        $db = $this->getDatabase();
        $query = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__docman_alert_deliveries'))
            ->where($db->quoteName('document_id') . ' = ' . $documentId)
            ->where($db->quoteName('milestone') . ' = ' . $days)
            ->where($db->quoteName('user_id') . ' = ' . $userId);
        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }
}
