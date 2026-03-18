<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint\Profile;

use Psr\Log\LoggerInterface;
use Schoolbox\Admin\Import\Profile\OrphanRemover;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;

/**
 * Class Cleanup
 *
 * Remove any stale profiles
 *
 * @package Schoolbox\External\Connector\Edlink\Base\Endpoint\Profile
 */
class Cleanup implements IStrategy
{
    /**
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
     * Remove stale profiles
     */
    public function import(): void
    {
        if ($this->perform) {
            $this->studentProfileMapper->getDatabase()->query("DELETE FROM {$this->studentProfileMapper->getTable()} WHERE updated_at = '0000-00-00 00:00:00' OR updated_at IS NULL");
            $this->parentProfileMapper->getDatabase()->query("DELETE FROM {$this->parentProfileMapper->getTable()} WHERE updated_at = '0000-00-00 00:00:00' OR updated_at IS NULL");
            $this->staffProfileMapper->getDatabase()->query("DELETE FROM {$this->staffProfileMapper->getTable()} WHERE updated_at = '0000-00-00 00:00:00' OR updated_at IS NULL");
            $this->baseProfileMapper->getDatabase()->query("DELETE FROM {$this->baseProfileMapper->getTable()} WHERE updated_at = '0000-00-00 00:00:00' OR updated_at IS NULL");

            $orphans = (new OrphanRemover(
                $this->baseProfileMapper,
                $this->studentProfileMapper,
                $this->parentProfileMapper,
                $this->staffProfileMapper,
                $this->relationshipMapper
            ))->remove();

            $this->log->info("Removed {$orphans} orphaned profiles.");
        }
    }
}
