<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\TimetablePeriodMapper;

/**
 * Class Prepare
 *
 * Clears data from a db in preparation for an import
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint\Timetable
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
     * Prepare constructor.
     *
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param TimetablePeriodMapper           $periodMapper
     * @param ClassEntryMapper                $classEntryMapper
     * @param ClassInvolvementMapper          $classInvolvementMapper
     * @param ClassPeriodMapper               $classPeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        TimetablePeriodMapper $periodMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;

        $this->periodMapper = $periodMapper;
        $this->classEntryMapper = $classEntryMapper;
        $this->classInvolvementMapper = $classInvolvementMapper;
        $this->classPeriodMapper = $classPeriodMapper;
    }


    /**
     * Purge classes that were previously imported
     */
    public function import(): void
    {
        if ($this->perform) {
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
            $this->classPeriodMapper->truncate();
            $this->log->debug('Purged classPeriod relations');

            sleep(1);
            $this->classEntryMapper->truncate();
            $this->log->debug('Purged classEntry');

            sleep(1);
            $this->periodMapper->truncate();
            $this->log->debug('Purged classPeriod');

            $this->log->info('Purged existing classInvolvement, classPeriod, classEntry data');
        }
    }
}
