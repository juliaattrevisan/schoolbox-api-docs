<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\TimetableCalendar;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\Event\Mapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class Prepare
 *
 * Clears data from a db in preparation for an import
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\TimetableCalendar
 */
class Prepare implements IStrategy
{

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
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        \Schoolbox\External\Event\Mapper $eventMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->eventMapper = $eventMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->overridesMapper = $overridesMapper;
    }


    /**
     * Purge classes that were previously imported
     */
    public function import(): void
    {
        if ($this->perform) {
            /* Delete existing timetable events
             */
            $this->eventMapper->getDatabase()->prepare("
                DELETE FROM {$this->eventMapper->getTable()}
                WHERE classification = :classification
            ")->execute([
                'classification' => \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE
            ]);

            $this->log->debug(sprintf(
                'Purged timetable events classified as %s',
                \Schoolbox\External\Event\Model::CLASSIFICATION_TIMETABLE
            ));

            /* Delete any overrides that existed
             */
            $this->overridesMapper->getDatabase()->query("
                DELETE FROM {$this->overridesMapper->getTable()}
            ");

            $this->log->debug('Purged timetable event overrides');

            /* Truncate other data entirely
             *
             * For some reason this truncate() doesn't always execute for all the tables
             * This leaves stuff in a broken state which you have to manually clear
             *
             * It manifests as a FK issue where it tries to insert duplicate relations
             * that were supposed to have been deleted prior
             */
            sleep(1);
            $this->classInvolvementMapper->truncate();
            $this->log->debug('Purged classInvolvement relations');

            sleep(1);
            $this->classEntryMapper->truncate();
            $this->log->debug('Purged classEntry');

            $this->log->info('Purged existing timetable events, classEntry, classInvolvement, and overrides data');
        }
    }
}
