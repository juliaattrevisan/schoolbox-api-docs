<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Liss\Base\Endpoint\Factory;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetablePeriodMapper;

/**
 * Class Prepare
 *
 * Clears data from a db in preparation for an import
 *
 * @package Schoolbox\External\Connector\Liss\Base\Endpoint\TimetableCalendar
 */
class Prepare implements IStrategy
{
    /**
     * Prepare constructor.
     *
     * @param LoggerInterface                            $log
     * @param bool                                       $perform
     * @param Mapper                                     $eventMapper
     * @param ClassEntryMapper                           $classEntryMapper
     * @param ClassInvolvementMapper                     $classInvolvementMapper
     * @param ClassPeriodMapper                          $classPeriodMapper
     * @param TimetablePeriodMapper                      $periodMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     */
    public function __construct(
        private LoggerInterface $log,
        private bool $perform,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper,
        private ClassPeriodMapper $classPeriodMapper,
        private TimetablePeriodMapper $periodMapper,
        private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
    ) {
    }


    /**
     * Purge classes that were previously imported
     */
    public function import(): void
    {
        if ($this->perform) {
            /* Delete anything to do with suds_liss that we previously imported
             * This is a bit special, because of user-configurable source names.
             * (Redlands)
             */
            $source = Factory::SOURCE_NAME;

            $query = "
                UPDATE {$this->eventMapper->getTable()}
                INNER JOIN {$this->overridesMapper->getTable()} seuo ON seuo.event_id = {$this->eventMapper->getTable()}.id
                SET {$this->eventMapper->getTable()}.updated_at = '0000-00-00 00:00:00'
                WHERE {$this->eventMapper->getTable()}.classification = :classification
                AND {$this->eventMapper->getTable()}.`source` LIKE '{$source}%'
            ";

            $statement = $this->eventMapper->getDatabase()->prepare($query);
            $statement->execute([
                'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
            ]);

            /* Delete any overrides that existed
             */
            $query = "
                DELETE seuo
                FROM {$this->overridesMapper->getTable()} seuo
                INNER JOIN {$this->eventMapper->getTable()} ON seuo.event_id = {$this->eventMapper->getTable()}.id
                WHERE {$this->eventMapper->getTable()}.classification = :classification
                AND {$this->eventMapper->getTable()}.`source` LIKE '{$source}%'
            ";

            $statement = $this->overridesMapper->getDatabase()->prepare($query);
            $statement->execute([
                'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
            ]);

            $this->log->debug('Purged timetable event overrides');

            /* Truncate other data entirely
             */
            $this->classEntryMapper->truncate();
            $this->classInvolvementMapper->truncate();
            $this->classPeriodMapper->truncate();
            $this->periodMapper->truncate();

            $this->log->info('Purged existing timetable events, classEntry, classInvolvement, classPeriod, period and overrides data');
        }
    }
}
