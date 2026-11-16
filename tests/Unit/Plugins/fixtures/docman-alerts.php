<?php

namespace Joomla\CMS\Plugin {
    class CMSPlugin
    {
        public function __construct(array $config) {}
    }
}

namespace Joomla\CMS {
    class Factory
    {
        public static $container;
        public static function getContainer() { return self::$container; }
    }
}

namespace Joomla\CMS\Installer {
    class InstallerAdapter {}
    interface InstallerScriptInterface
    {
        public function install(InstallerAdapter $adapter): bool;
        public function update(InstallerAdapter $adapter): bool;
        public function uninstall(InstallerAdapter $adapter): bool;
        public function preflight(string $type, InstallerAdapter $adapter): bool;
        public function postflight(string $type, InstallerAdapter $adapter): bool;
    }
}

namespace Joomla\CMS\Language {
    class Text
    {
        public static function sprintf(string $key, ...$args): string
        {
            return $key . ':' . implode('|', $args);
        }
    }
}

namespace Joomla\CMS\Mail {
    interface MailerFactoryInterface
    {
        public function createMailer();
    }

    class Mail
    {
        public static array $sent = [];
        public static array $fail = [];
        private string $recipient;
        public string $body;

        public function isHtml(bool $html): void {}
        public function addRecipient(string $email, string $name): void { $this->recipient = $email; }
        public function setSubject(string $subject): void {}
        public function setBody(string $body): void { $this->body = $body; }

        public function send(): bool
        {
            if (isset(self::$fail[$this->recipient])) {
                return false;
            }

            self::$sent[] = [$this->recipient, $this->body];

            return true;
        }
    }
}

namespace Joomla\Component\Scheduler\Administrator\Event {
    class ExecuteTaskEvent {}
}

namespace Joomla\Component\Scheduler\Administrator\Task {
    class Status
    {
        public const OK = 0;
        public const KNOCKOUT = 1;
    }
}

namespace Joomla\Component\Scheduler\Administrator\Traits {
    trait TaskPluginTrait
    {
        public array $messages = [];

        protected function logTask(string $message): void
        {
            $this->messages[] = $message;
        }
    }
}

namespace Joomla\Database {
    trait DatabaseAwareTrait
    {
        private $database;
        public function setDatabase($database): void { $this->database = $database; }
        public function getDatabase() { return $this->database; }
    }
}

namespace Joomla\Event {
    interface SubscriberInterface {}
}

namespace {
    use Joomla\CMS\Mail\Mail;
    use Joomla\CMS\Mail\MailerFactoryInterface;
    use Joomla\CMS\Factory;
    use Joomla\CMS\Installer\InstallerAdapter;
    use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
    use Joomla\Component\Scheduler\Administrator\Task\Status;
    use Kunena\Plugin\Task\DocmanAlerts\Extension\DocmanAlerts;

    define('_JEXEC', 1);
    require __DIR__ . '/../../../../src/plugins/plg_task_docmanalerts/src/AlertRules.php';
    require __DIR__ . '/../../../../src/plugins/plg_task_docmanalerts/src/Extension/DocmanAlerts.php';

    class Query
    {
        private string $operation = '';
        private string $select = '';
        private string $table = '';
        private array $joins = [];
        private array $where = [];
        private array $set = [];
        private array $columns = [];
        private string $values = '';

        public function select($value): self { $this->operation = 'SELECT'; $this->select = $value; return $this; }
        public function from($value): self { $this->table = $value; return $this; }
        public function innerJoin($value): self { $this->joins[] = 'INNER JOIN ' . $value; return $this; }
        public function where($value): self { $this->where[] = $value; return $this; }
        public function update($value): self { $this->operation = 'UPDATE'; $this->table = $value; return $this; }
        public function set($value): self { $this->set[] = $value; return $this; }
        public function insert($value): self { $this->operation = 'INSERT'; $this->table = $value; return $this; }
        public function columns($value): self { $this->columns = $value; return $this; }
        public function values($value): self { $this->values = $value; return $this; }

        public function __toString(): string
        {
            $where = $this->where ? ' WHERE ' . implode(' AND ', $this->where) : '';

            return match ($this->operation) {
                'SELECT' => 'SELECT ' . $this->select . ' FROM ' . $this->table . ' ' . implode(' ', $this->joins) . $where,
                'UPDATE' => 'UPDATE ' . $this->table . ' SET ' . implode(', ', $this->set) . $where,
                'INSERT' => 'INSERT INTO ' . $this->table . ' (' . implode(', ', $this->columns) . ') VALUES (' . $this->values . ')',
            };
        }
    }

    class Database
    {
        private string $sql;
        public function __construct(public \PDO $pdo) {}
        public function createQuery(): Query { return new Query(); }
        public function quote($value): string { return $this->pdo->quote($value); }
        public function quoteName($name, $alias = null)
        {
            if (\is_array($name)) {
                return array_map(fn ($item) => $this->quoteName($item), $name);
            }

            $parts = explode('.', str_replace('#__', 'jos_', $name));
            $quoted = implode('.', array_map(fn ($part) => '`' . $part . '`', $parts));

            return $alias === null ? $quoted : $quoted . ' AS `' . $alias . '`';
        }
        public function setQuery($query): self
        {
            $this->sql = str_replace([' FOR UPDATE', ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'], '', (string) $query);
            return $this;
        }
        public function execute(): bool { return $this->pdo->exec($this->sql) !== false; }
        public function loadColumn(): array { return $this->pdo->query($this->sql)->fetchAll(\PDO::FETCH_COLUMN); }
        public function loadObject() { return $this->pdo->query($this->sql)->fetchObject() ?: null; }
        public function loadObjectList(): array { return $this->pdo->query($this->sql)->fetchAll(\PDO::FETCH_OBJ); }
        public function loadResult() { return $this->pdo->query($this->sql)->fetchColumn(); }
        public function transactionStart(): void { $this->pdo->beginTransaction(); }
        public function transactionCommit(): void { $this->pdo->commit(); }
        public function transactionRollback(): void { $this->pdo->rollBack(); }
        public function replacePrefix(string $name): string { return str_replace('#__', 'jos_', $name); }
        public function getTableList(): array { return $this->pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN); }
        public function getTableColumns(string $name): array
        {
            $columns = $this->pdo->query('PRAGMA table_info(' . $this->quoteName($name) . ')')->fetchAll(\PDO::FETCH_ASSOC);
            return array_column($columns, 'type', 'name');
        }
    }

    function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    $pdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    $pdo->sqliteCreateFunction('FIND_IN_SET', fn ($id, $list) => \in_array((string) $id, explode(',', $list), true) ? 1 : 0);
    $pdo->exec('CREATE TABLE jos_docman_documents (docman_document_id INTEGER PRIMARY KEY, title TEXT, docman_category_id INTEGER, storage_path TEXT, unpublish_on TEXT, alert_90_sent TEXT, alert_30_sent TEXT)');
    $pdo->exec('CREATE TABLE jos_docman_categories (docman_category_id INTEGER PRIMARY KEY, access INTEGER)');
    $pdo->exec('CREATE TABLE jos_docman_levels (docman_level_id INTEGER PRIMARY KEY, groups TEXT)');
    $pdo->exec('CREATE TABLE jos_user_usergroup_map (user_id INTEGER, group_id INTEGER)');
    $pdo->exec('CREATE TABLE jos_users (id INTEGER PRIMARY KEY, name TEXT, email TEXT, block INTEGER)');
    $pdo->exec('CREATE TABLE jos_docman_alert_deliveries (document_id INTEGER, milestone INTEGER, user_id INTEGER, sent_at TEXT, PRIMARY KEY (document_id, milestone, user_id))');
    $pdo->exec("INSERT INTO jos_docman_categories VALUES (1, -3)");
    $pdo->exec("INSERT INTO jos_docman_levels VALUES (3, '7,8')");
    $pdo->exec("INSERT INTO jos_users VALUES (1, 'One', 'one@example.com', 0), (2, 'Two', 'two@example.com', 0), (3, 'Blocked', 'blocked@example.com', 1), (4, 'Other', 'other@example.com', 0)");
    $pdo->exec("INSERT INTO jos_user_usergroup_map VALUES (1, 7), (1, 8), (2, 8), (3, 7), (4, 9)");
    $document = $pdo->prepare('INSERT INTO jos_docman_documents VALUES (1, ?, 1, ?, ?, NULL, NULL)');
    $document->execute(['Convention <test>', '\\\\server\\share\\file #1.pdf', gmdate('Y-m-d H:i:s', time() + 60 * 86400)]);

    $factory = new class implements MailerFactoryInterface {
        public function createMailer() { return new Mail(); }
    };
    $plugin = new DocmanAlerts([], $factory);
    $plugin->setDatabase(new Database($pdo));
    $run = new \ReflectionMethod($plugin, 'sendAlerts');
    $event = new ExecuteTaskEvent();

    Mail::$fail['two@example.com'] = true;
    check($run->invoke($plugin, $event) === Status::KNOCKOUT, 'A partial mail failure must fail the task.');
    check(\count(Mail::$sent) === 1 && Mail::$sent[0][0] === 'one@example.com', 'Only one active, matching recipient should be sent.');
    check(str_contains(Mail::$sent[0][1], 'file://server/share/file%20%231.pdf'), 'The message must contain the encoded network link.');
    check(str_contains(Mail::$sent[0][1], 'Convention &lt;test&gt;'), 'The title must be escaped.');
    check($pdo->query('SELECT alert_90_sent FROM jos_docman_documents')->fetchColumn() === null, 'A partial failure must leave the marker NULL.');
    check((int) $pdo->query('SELECT COUNT(*) FROM jos_docman_alert_deliveries')->fetchColumn() === 1, 'Successful individual sends must be recorded.');

    Mail::$fail = [];
    check($run->invoke($plugin, $event) === Status::OK, 'The failed recipient should be retried.');
    check(\count(Mail::$sent) === 2 && Mail::$sent[1][0] === 'two@example.com', 'A successful recipient must not receive the retry.');
    check($pdo->query('SELECT alert_90_sent FROM jos_docman_documents')->fetchColumn() !== null, 'The marker must be set after all recipients succeed.');
    check($pdo->query('SELECT alert_30_sent FROM jos_docman_documents')->fetchColumn() === null, 'J-30 must not be sent early.');

    $pdo->prepare('UPDATE jos_docman_documents SET unpublish_on = ?')->execute([gmdate('Y-m-d H:i:s', time() + 20 * 86400)]);
    check($run->invoke($plugin, $event) === Status::OK && \count(Mail::$sent) === 4, 'The J-30 alert must be sent to both recipients.');
    check($pdo->query('SELECT alert_30_sent FROM jos_docman_documents')->fetchColumn() !== null, 'The J-30 marker must be set.');
    check($run->invoke($plugin, $event) === Status::OK && \count(Mail::$sent) === 4, 'Completed alerts must not be resent.');

    $document = $pdo->prepare('INSERT INTO jos_docman_documents VALUES (?, ?, ?, ?, ?, NULL, NULL)');
    $soon = gmdate('Y-m-d H:i:s', time() + 10 * 86400);
    $document->execute([2, 'Catch-up', 1, '\\\\server\\share\\file.pdf', $soon]);
    $document->execute([3, 'Invalid path', 1, 'not-a-network-path', $soon]);
    $document->execute([4, 'No category', 99, '\\\\server\\share\\file.pdf', $soon]);
    $document->execute([5, 'Expired', 1, '\\\\server\\share\\file.pdf', gmdate('Y-m-d H:i:s', time() - 10)]);
    check($run->invoke($plugin, $event) === Status::KNOCKOUT, 'Invalid paths and missing recipients must fail the task.');
    check(\count(Mail::$sent) === 8, 'Both missed alerts must be sent to both matching users.');
    check((int) $pdo->query('SELECT COUNT(*) FROM jos_docman_alert_deliveries WHERE document_id = 2')->fetchColumn() === 4, 'Both catch-up milestones must be recorded.');
    check((int) $pdo->query('SELECT COUNT(*) FROM jos_docman_documents WHERE docman_document_id IN (3, 4, 5) AND (alert_90_sent IS NOT NULL OR alert_30_sent IS NOT NULL)')->fetchColumn() === 0, 'Invalid, recipient-less and expired documents must remain unmarked.');

    $installPdo = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
    $installPdo->exec('CREATE TABLE jos_docman_documents (docman_document_id INTEGER PRIMARY KEY)');
    $installDb = new Database($installPdo);
    Factory::$container = new class ($installDb) {
        public function __construct(private Database $db) {}
        public function get(string $name): Database { return $this->db; }
    };
    $installer = require __DIR__ . '/../../../../src/plugins/plg_task_docmanalerts/script.php';
    $adapter = new InstallerAdapter();
    check($installer->preflight('install', $adapter), 'Installation must succeed.');
    check(isset($installDb->getTableColumns('jos_docman_documents')['alert_90_sent'], $installDb->getTableColumns('jos_docman_documents')['alert_30_sent']), 'Installation must create both marker columns.');
    check(\in_array('jos_docman_alert_deliveries', $installDb->getTableList(), true), 'Installation must create the delivery ledger.');
    check($installer->preflight('update', $adapter), 'Reinstallation must be idempotent.');

    echo "DOCman alert integration checks passed\n";
}
