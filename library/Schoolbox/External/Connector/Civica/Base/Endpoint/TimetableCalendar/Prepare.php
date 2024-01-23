<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Civica\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Event\Model;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetablePeriodMapper;

/**
 * Class Prepare
 *
 * Clears data from a db in preparation for an import
 *
 * @package Schoolbox\External\Connector\Civica\Base\Endpoint\TimetableCalendar
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
            /* Delete any overrides that existed
             */
            $this->overridesMapper->truncate();
            $this->log->debug('Purged timetable event overrides');

            /* Delete existing timetable events
             */
            $this->eventMapper->deleteByColumn('classification', Model::CLASSIFICATION_TIMETABLE);
            $this->log->debug(sprintf(
                'Purged timetable events classified as %s',
                Model::CLASSIFICATION_TIMETABLE
            ));

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
