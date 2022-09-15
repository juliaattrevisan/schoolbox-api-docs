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
     * Event source is an arbitrary string that helps identify the thing that imported
     * them into the local event table so you can do a mass remove if needed
     *
     * @var string
     */
    private string $source;


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
     * @param string                                     $source
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        \Schoolbox\External\Event\Mapper $eventMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper,
        TimetablePeriodMapper $periodMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        string $source
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->eventMapper = $eventMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
        $this->periodMapper = $periodMapper;
        $this->overridesMapper = $overridesMapper;

        /* Event source is an arbitrary string that helps identify the thing that imported
         * them into the local event table so you can do a mass remove if needed
         */
        $this->source = $source;
    }


    /**
     * Purge classes that were previously imported
     */
    public function import(): void
    {
        if ($this->perform) {
            /* Delete any overrides that existed
             */
            $query = "
                DELETE seuo
                FROM {$this->overridesMapper->getTable()} seuo
                INNER JOIN {$this->eventMapper->getTable()} ON seuo.event_id = {$this->eventMapper->getTable()}.id
                WHERE {$this->eventMapper->getTable()}.classification = :classification
                AND {$this->eventMapper->getTable()}.`source` = :source
            ";

            $statement = $this->overridesMapper->getDatabase()->prepare($query);
            $statement->execute([
                'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
                'source' => $this->source
            ]);

            $this->log->debug('Purged timetable event overrides');

            $query = "
                UPDATE {$this->eventMapper->getTable()}
                INNER JOIN {$this->overridesMapper->getTable()} seuo ON seuo.event_id = {$this->eventMapper->getTable()}.id
                SET {$this->eventMapper->getTable()}.updated_at = '0000-00-00 00:00:00'
                WHERE {$this->eventMapper->getTable()}.classification = :classification
                AND {$this->eventMapper->getTable()}.`source` = :source  
            ";

            $statement = $this->eventMapper->getDatabase()->prepare($query);
            $statement->execute([
                'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE,
                'source' => $this->source,
            ]);

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
