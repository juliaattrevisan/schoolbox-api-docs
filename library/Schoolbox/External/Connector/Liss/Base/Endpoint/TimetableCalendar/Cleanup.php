<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class Cleanup
 *
 * Remove any stale timetable events
 *
 * @package Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar
 */
class Cleanup implements IStrategy
{
    /**
     * Cleanup constructor.
     *
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param string          $sourceName
     * @param Mapper          $eventMapper
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private string $sourceName,
        private \Schoolbox\External\Event\Mapper $eventMapper
    ) {
    }


    /**
     * Remove stale profiles
     */
    public function import(): void
    {
        /* Only remove profiles if we're performing
         */
        if ($this->perform) {
            /* Remove everything that wasn't touched
             */
            $statement = $this->eventMapper->getDatabase()->prepare("
                DELETE FROM {$this->eventMapper->getTable()}
                WHERE (updated_at = '0000-00-00 00:00:00' OR updated_at IS NULL)
                AND classification = :classification
                AND source = :source
            ");

            $statement->execute([
                'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
                'source' => $this->sourceName
            ]);

            $this->log->info("Removed stale timetable events.");
        }
    }
}
