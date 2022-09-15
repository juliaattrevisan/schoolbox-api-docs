<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetablePeriodMapper;

/**
 * Class Prepare
 *
 * Clears data from a db in preparation for an import
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint\TimetableCalendar
 */
class Prepare implements IStrategy
{
    /**
     * An arbitrarily-chosen string to denote where these events came from
     */
    public const EVENT_IMPORT_IDENTIFIER = 'suds_compass';


    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * @var bool
     */
    private bool $perform;


    /**
     * @var Mapper
     */
    private \Schoolbox\External\Event\Mapper $eventMapper;


    /**
     * @var ClassEntryMapper
     */
    private ClassEntryMapper $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private ClassInvolvementMapper $classInvolvementMapper;


    /**
     * @var ClassPeriodMapper
     */
    private ClassPeriodMapper $classPeriodMapper;


    /**
     * @var TimetablePeriodMapper
     */
    private TimetablePeriodMapper $periodMapper;


    /**
     * @var \Schoolbox\External\Event\Overrides\Mapper
     */
    private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper;


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
        LoggerInterface $log,
        bool $perform,
        \Schoolbox\External\Event\Mapper $eventMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper,
        TimetablePeriodMapper $periodMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->eventMapper = $eventMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
        $this->periodMapper = $periodMapper;
        $this->overridesMapper = $overridesMapper;
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
            $this->eventMapper->deleteByColumn('classification', \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE);
            $this->log->debug(sprintf(
                'Purged timetable events classified as %s',
                \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE
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
