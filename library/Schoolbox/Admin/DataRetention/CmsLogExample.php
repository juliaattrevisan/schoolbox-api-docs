<?php

/**
 * example strategy, clear things from the cmsLog older than 2 years
 *
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

use Alaress\Database\Connector\IConnector;
use Psr\Log\LoggerInterface;
use Schoolbox\IConfigLoader;
use Symfony\Component\Lock\LockInterface;

class CmsLogExample implements IStrategy
{
    /**
     * @param null|LoggerInterface $log
     * @param bool                 $perform
     * @param IConnector           $database
     * @param LockInterface        $lock
     * @param IConfigLoader        $config
     * @param int                  $chunkLoops
     */
    public function __construct(private ?LoggerInterface $log, private bool $perform, private IConnector $database, private LockInterface $lock, private IConfigLoader $config, private int $chunkLoops = 3)
    {
    }

    /**
     * @inheritDoc
     */
    public function process(): void
    {
        $retentionPeriod = (string)$this->config->get('dataRetention_cmsLogRetentionPeriod'); // configured by admin with planned options 1 Day, 30 Days, 365 Days, <null> (null is do NOT hard delete this)
        if ('' === $retentionPeriod) {
            $this->log->notice('No retention period set, skipping');
            return;
        }

        $this->lock->refresh();

        $deleteCount = 0;

        $deleteOlderThan = (\Alaress_Date_Format::newNaturalDate(sprintf("-%s days", $retentionPeriod)))->getDatabase();

        // find how cmsLog Entries are stored in the database
        $query = "
            SELECT COUNT(*)
            FROM cmsLog
            WHERE time < :old
        ";

        $statement = $this->database->prepare($query);
        $statement->execute([
                'old'   => $deleteOlderThan,
            ]);

        $this->log->info('Found ' . $statement->fetchColumn() . ' logs to delete');

        // if perform is true, delete logs older than the retention period
        if ($this->perform) {
            $this->log->info('Deleting logs older than ' . $deleteOlderThan);
            $deleteQuery = "
                DELETE FROM cmsLog
                WHERE time < :old
            ";
            $statement = $this->database->prepare($deleteQuery);
            $statement->execute([
                    'old'   => $deleteOlderThan,
                ]);
            $deleteCount = $statement->rowCount();
        } else {
            $this->log->info('Would have deleted older than ' . $deleteOlderThan);
        }

        // log the number of log entries deleted
        $this->log->info('Deleted ' . $deleteCount . ' Log Entries');
    }

    /**
     * @inheritDoc
     */
    public function getRetentionLabel(): string
    {
        return 'CMSLog Retention - This is an example strategy';
    }
}
