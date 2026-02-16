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

use Psr\Log\LoggerInterface;

class cmsLogExample implements IStrategy
{
    public function __construct(private LoggerInterface $log, private bool $perform, private \Schoolbox\CMS\Log\Mapper $cmsLog, private string $retentionPeriod = '')
    {
    }

    public function process(): void
    {
        if ('' === $this->retentionPeriod) {
            $this->log->info('No retention period set, skipping');
            return;
        }

        $database = $this->cmsLog->getDatabase();
        $deleteCount = 0;

        $deleteOlderThan = (\Alaress_Date_Format::newNaturalDate(sprintf("%s", $this->retentionPeriod)))->getDatabase();

        // find how cmsLog Entries are stored in the database
        $query = "
            SELECT COUNT(*)
            FROM cmsLog
            WHERE time < :old
        ";

        $statement = $database->prepare($query);
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
            $statement = $database->prepare($deleteQuery);
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
}
