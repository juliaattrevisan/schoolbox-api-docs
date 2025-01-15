<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint\Profile;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;

/**
 * Class Prepare
 *
 * Clears data from a db in preparation for a kamar import
 *
 * @package Schoolbox\External\Connector\Isams\Base\Endpoint\Profile
 */
class Prepare implements IStrategy
{
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
        private LoggerInterface $log,
        private bool $perform,
        private UserProfile\Mapper $baseProfileMapper,
        private UserProfile\Student\Mapper $studentProfileMapper,
        private UserProfile\Parent\Mapper $parentProfileMapper,
        private UserProfile\Staff\Mapper $staffProfileMapper,
        private UserProfile\Relationship\Mapper $relationshipMapper
    ) {
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

            $this->log->info('Purged existing profiles and profile relations');
        }
    }
}
