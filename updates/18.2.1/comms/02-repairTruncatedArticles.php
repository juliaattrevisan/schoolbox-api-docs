#!/usr/bin/php
<?php

/**
 * Repair script for repairing articles that were truncated in the migration
 * from homepage and school news to comms articles.
 *
 * Until now, comms articles have been stored as TEXT (2^16 bytes, ~64KB) when
 * the old tables were MEDIUMTEXT (2^24 bytes, ~16MB): this means that the new
 * comms articles will have been truncated.
 *
 * Fortunately the old tables and their data still exist, so their article
 * content can be migrated.
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Aura\Di;
use Psr\Log\LoggerInterface as Logger;
use React\EventLoop\LoopInterface;
use Schoolbox\Application\Daemon\App;
use Schoolbox\Application\Daemon;

require_once __DIR__ . '/../../../conf/core/require.php';

$kernel = new Schoolbox\Application\Daemon\Kernel(
    'Repair Truncated Articles',
    [
        Daemon\Kernel::OPTION_LEVEL          => Monolog\Logger::DEBUG,
        Daemon\Kernel::OPTION_ERRORS         => E_ALL,
        Daemon\Kernel::OPTION_APP_SHORT_OPTS => 'p',
        Daemon\Kernel::OPTION_APP_LONG_OPTS  => ['perform'],
    ]
);

/** @noinspection PhpUnhandledExceptionInspection */
$kernel->run(new class extends App
{
    private const INDENT = '  ';

    private const FIND_MIGRATED_NEWS_QUERY = "
        SELECT
            ca.id                  AS new_id,
            sn.id                  AS old_id,
            'homepage'             AS old_type,
            COALESCE(sn.body, '')  AS old_body,
            COALESCE(ca.body, '')  AS new_body
        FROM       subject_news       sn
        INNER JOIN comms_news_article ca ON sn.migration_id = ca.id

        UNION

        SELECT
            ca.id                       AS new_id,
            ni.item_id                  AS old_id,
            'school'                    AS old_type,
            COALESCE(ni.item_body, '')  AS old_body,
            COALESCE(ca.body, '')       AS new_body
        FROM       news_items         ni
        INNER JOIN comms_news_article ca ON ni.item_id = ca.id

        ORDER BY new_id ASC
    ";

    private const UPDATE_COMMS_ARTICLE_BODY_QUERY = "
        UPDATE comms_news_article SET body = :body WHERE id = :id
    ";


    /**
     * @var Logger
     */
    private $log;


    /**
     * @var IConnector
     */
    private $db;


    /**
     * Is this actually performing an update?
     *
     * @var bool
     */
    private $perform = false;


    /**
     * Set up and run the repair process.
     *
     * Install all instance vars, and wraps the repair process in a transaction
     * for commit (if `--perform` is on) or rollback (if it's off, or an error
     * is raised).
     *
     * @inheritdoc
     */
    public function run(Di\Container $di, LoopInterface $loop, Logger $log, array $opts, string $flags): void
    {
        // Install logger and DB
        $this->log     = $log;
        $this->db      = $di->get('databaseInternal');
        $this->perform = isset($opts['p']) || isset($opts['perform']);

        // log perform / dry run
        if ($this->perform) {
            $this->log->warning('Running in perform mode (-p): changes will be written to database');
        } else {
            $this->log->warning('Running in dry-run mode: use -p to write changes to database');
        }


        // Update queries
        $updateBodyQuery = $this->db->prepareQuery(self::UPDATE_COMMS_ARTICLE_BODY_QUERY);


        // Find the list of migrated articles
        $this->db->beginTransaction();
        $updatedArticles   = [];
        $untouchedArticles = [];
        $migratedArticles  = $this->db->executeQuery(self::FIND_MIGRATED_NEWS_QUERY);
        foreach ($migratedArticles->getIterator() as $row) {
            $articleId = $row['new_id'];
            $this->log->notice(
                sprintf('Checking comms article #%d (%s article #%d)', $articleId, $row['old_type'], $row['old_id'])
            );

            $updated = false;
            $this->log->notice(self::indent(1) . 'checking body...');
            if ($this->isMigrationRequired($row['old_body'], $row['new_body'])) {
                $updateBodyQuery->execute([
                    'id'   => $articleId,
                    'body' => $row['old_body'],
                ]);
                $updated = true;
            }

            if ($updated) {
                $updatedArticles[$articleId] = true;
            } else {
                $untouchedArticles[$articleId] = true;
            }
        }


        if ($this->perform) {
            $this->db->commit();
        } else {
            $this->db->rollBack();
        }


        // output a summary
        $this->log->notice(
            sprintf(
                '%d articles updated, %d not updated',
                count($updatedArticles),
                count($untouchedArticles)
            )
        );
    }


    /**
     * Is migration required?
     *
     * Migration is only required if:
     * * `$old` cannot fit within `$new` column (it is 2^16 bytes or larger)
     * * `$new` is 2^16 bytes or more (if it's not, it can't be a truncation of
     *    `$old`)
     * * `$new` is a truncated form of `$old`; if it's not, we must assume the
     *    user has modified the article, and thus we can't assume they still
     *    want the old version
     *
     * @param  string $old The old field value
     * @param  string $new The new field value
     * @return bool
     */
    private function isMigrationRequired(string $old, string $new): bool
    {
        $truncatedLength = 2 ** 16 - 1;
        $oldLength       = strlen($old);
        $newLength       = strlen($new);
        if ($oldLength <= $truncatedLength) {
            $this->log->notice(self::indent(2) . 'old content fits within new content: no migrate required');
            return false;
        }

        $truncatedOld = substr($old, 0, $newLength);
        if ($truncatedOld === $new) {
            $this->log->notice(self::indent(2) . 'new is a substring of old: checking for truncation');

            if ($newLength === $truncatedLength) {
                $this->log->warning(self::indent(2) . 'new content is exactly the truncation length: migrating');
                return true;
            }

            $this->log->notice(self::indent(2) . 'new content is not exactly the truncation length: not migrating');
            return false;
        }

        $this->log->notice(self::indent(2) . 'new has been changed from old: not migrating');
        return false;
    }


    /**
     * Helper method for indenting log output for legibility.
     *
     * @param int $depth The depth to indent by
     * @return string
     */
    private static function indent(int $depth): string
    {
        return str_repeat(self::INDENT, $depth);
    }
});
