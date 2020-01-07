<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;

/**
 * Class Purge
 *
 * Clears data from a db in preparation for a kamar import
 *
 * @package Schoolbox\External\Connector\Kamar\Base\Endpoint\Timetable
 */
class Purge implements IStrategy
{

    /**
     * @var LoggerInterface
     */
    private $log;


    /**
     * @var bool
     */
    private $perform;


    /**
     * @var ClassEntryMapper
     */
    private $classEntryMapper;


    /**
     * @var ClassInvolvementMapper
     */
    private $classInvolvementMapper;


    /**
     * @var ClassPeriodMapper
     */
    private $classPeriodMapper;


    /**
     * Purge constructor.
     *
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param ClassPeriodMapper      $classPeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
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
            $this->classEntryMapper->getDatabase()->query("DELETE FROM {$this->classEntryMapper->getTable()}");
            $this->classInvolvementMapper->getDatabase()->query("DELETE FROM {$this->classInvolvementMapper->getTable()}");
            $this->classPeriodMapper->getDatabase()->query("DELETE FROM {$this->classPeriodMapper->getTable()}");

            $this->log->info('Purged existing classEntry, classInvolvement, classPeriod data');
        }
    }
}
