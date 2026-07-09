<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class Cleanup
 *
 * Remove any stale timetable events
 *
 * @package Schoolbox\External\Connector\Edlink\Base\Endpoint\TimetableCalendar
 */
class Cleanup implements IStrategy
{
    /**
     * @param LoggerInterface              $log
     * @param bool                         $perform
     * @param \Schoolbox\External\Event\Mapper $eventMapper
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private \Schoolbox\External\Event\Mapper $eventMapper
    ) {
    }

    /**
     * Remove stale timetable events
     */
    public function import(): void
    {
        if ($this->perform) {
            $statement = $this->eventMapper->getDatabase()->prepare("
                DELETE FROM {$this->eventMapper->getTable()}
                WHERE (updated_at = '0000-00-00 00:00:00' OR updated_at IS NULL)
                AND classification = :classification
                AND source = :source
            ");

            $statement->execute([
                'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
                'source' => \Schoolbox\External\Connector\Edlink\Base\Endpoint\Factory::SOURCE_NAME
            ]);

            $this->log->info("Removed stale timetable events.");
        }
    }
}
