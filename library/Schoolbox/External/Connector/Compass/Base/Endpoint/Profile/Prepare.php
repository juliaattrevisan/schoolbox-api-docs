<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Compass\Base\Endpoint\Profile;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;

/**
 * Class Prepare
 *
 * Clears data from a db in preparation for an import
 *
 * @package Schoolbox\External\Connector\Compass\Base\Endpoint\Profile
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
     * @var UserProfile\Mapper
     */
    private UserProfile\Mapper $baseProfileMapper;


    /**
     * @var UserProfile\Student\Mapper
     */
    private UserProfile\Student\Mapper $studentProfileMapper;


    /**
     * @var UserProfile\Parent\Mapper
     */
    private UserProfile\Parent\Mapper $parentProfileMapper;


    /**
     * @var UserProfile\Staff\Mapper
     */
    private UserProfile\Staff\Mapper $staffProfileMapper;


    /**
     * @var UserProfile\Relationship\Mapper
     */
    private UserProfile\Relationship\Mapper $relationshipMapper;


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
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        UserProfile\Mapper $baseProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;

        $this->baseProfileMapper = $baseProfileMapper;
        $this->studentProfileMapper = $studentProfileMapper;
        $this->parentProfileMapper = $parentProfileMapper;
        $this->staffProfileMapper = $staffProfileMapper;
        $this->relationshipMapper = $relationshipMapper;
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

            /* Clear students' emergency contacts
             *
             * Alternatively I could have done it in Profile.php but it just makes that
             * algorithm longer, plus you can make less-obvious mistakes about it
             */
            $this->studentProfileMapper->getDatabase()->query("
                UPDATE {$this->studentProfileMapper->getTable()}
                SET 
                    emergency_contact_name_1 = NULL,
                    emergency_contact_phone_1 = NULL,
                    emergency_contact_phone_alt_1 = NULL,
                    emergency_contact_name_2 = NULL,
                    emergency_contact_phone_2 = NULL,
                    emergency_contact_phone_alt_2 = NULL,
                    emergency_contact_name_3 = NULL,
                    emergency_contact_phone_3 = NULL,
                    emergency_contact_phone_alt_3 = NULL
            ");

            /* Truncate other data entirely
             */
            $this->relationshipMapper->truncate();

            $this->log->info('Purged existing profile relations and user profile data');
        }
    }
}
