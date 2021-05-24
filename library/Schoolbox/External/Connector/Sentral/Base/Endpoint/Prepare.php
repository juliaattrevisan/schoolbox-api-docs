<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Sentral\Base\Endpoint;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetablePeriodMapper;

/**
 * Class Prepare
 *
 * Clears data from a db in preparation for an import
 *
 * @package Schoolbox\External\Connector\Sentral\Base\Endpoint
 */
class Prepare implements IStrategy
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
     * @var UserProfile\Mapper
     */
    private $baseProfileMapper;


    /**
     * @var UserProfile\Student\Mapper
     */
    private $studentProfileMapper;


    /**
     * @var UserProfile\Parent\Mapper
     */
    private $parentProfileMapper;


    /**
     * @var UserProfile\Staff\Mapper
     */
    private $staffProfileMapper;


    /**
     * @var UserProfile\Relationship\Mapper
     */
    private $relationshipMapper;


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
     * @var TimetablePeriodMapper
     */
    private $periodMapper;


    /**
     * Prepare constructor.
     *
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     * @param TimetablePeriodMapper           $periodMapper
     * @param ClassEntryMapper                $classEntryMapper
     * @param ClassInvolvementMapper          $classInvolvementMapper
     * @param ClassPeriodMapper               $classPeriodMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper,
        TimetablePeriodMapper $periodMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;

        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;

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
            /* Set all the updated_at fields to null in all the profiles everywhere
             * Everyone we don't update gets deleted in Cleanup
             */
            $this->studentProfileMapper->getDatabase()->query("UPDATE {$this->studentProfileMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
            $this->parentProfileMapper->getDatabase()->query("UPDATE {$this->parentProfileMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
            $this->staffProfileMapper->getDatabase()->query("UPDATE {$this->staffProfileMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");
            $this->baseProfileMapper->getDatabase()->query("UPDATE {$this->baseProfileMapper->getTable()} SET updated_at = '0000-00-00 00:00:00'");

            /* Clear emergency contacts due the way we encounter them mixed up with regular parents
             * If only this were normalised...
             */
            $this->studentProfileMapper->getDatabase()->query("UPDATE {$this->studentProfileMapper->getTable()} SET emergency_contact_name_1 = NULL");
            $this->studentProfileMapper->getDatabase()->query("UPDATE {$this->studentProfileMapper->getTable()} SET emergency_contact_phone_1 = NULL");
            $this->studentProfileMapper->getDatabase()->query("UPDATE {$this->studentProfileMapper->getTable()} SET emergency_contact_phone_alt_1 = NULL");

            $this->studentProfileMapper->getDatabase()->query("UPDATE {$this->studentProfileMapper->getTable()} SET emergency_contact_name_2 = NULL");
            $this->studentProfileMapper->getDatabase()->query("UPDATE {$this->studentProfileMapper->getTable()} SET emergency_contact_phone_2 = NULL");
            $this->studentProfileMapper->getDatabase()->query("UPDATE {$this->studentProfileMapper->getTable()} SET emergency_contact_phone_alt_2 = NULL");

            $this->studentProfileMapper->getDatabase()->query("UPDATE {$this->studentProfileMapper->getTable()} SET emergency_contact_name_3 = NULL");
            $this->studentProfileMapper->getDatabase()->query("UPDATE {$this->studentProfileMapper->getTable()} SET emergency_contact_phone_3 = NULL");
            $this->studentProfileMapper->getDatabase()->query("UPDATE {$this->studentProfileMapper->getTable()} SET emergency_contact_phone_alt_3 = NULL");

            /* Truncate other data entirely
             *
             * For some reason this truncate() doesn't always execute for all the tables
             * This leaves stuff in a broken state which you have to manually clear
             *
             * It manifests as a FK issue where it tries to insert duplicate relations
             * that were supposed to have been deleted prior
             */
            sleep(1);
            $this->relationshipMapper->truncate();
            $this->log->debug('Purged profile relations');

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

            $this->log->info('Purged existing profile relations, classInvolvement, classPeriod, classEntry data');
        }
    }
}
