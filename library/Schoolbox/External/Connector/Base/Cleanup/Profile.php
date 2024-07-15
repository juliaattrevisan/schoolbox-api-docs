<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Base\Cleanup;

use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper\ShadowTable;

/**
 * Cleans up the profile mapper data, by migrating from the shadow table to the
 * base table.
 */
class Profile implements IStrategy
{
    /**
     * Constructor
     *
     * @param UserProfile\Mapper              $userProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipProfileMapper
     */
    public function __construct(
        private UserProfile\Mapper $userProfileMapper,
        private UserProfile\Student\Mapper $studentProfileMapper,
        private UserProfile\Parent\Mapper $parentProfileMapper,
        private UserProfile\Staff\Mapper $staffProfileMapper,
        private UserProfile\Relationship\Mapper $relationshipProfileMapper,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function import(): void
    {
        $shadowTable = new ShadowTable(
            $this->userProfileMapper,
            $this->studentProfileMapper,
            $this->parentProfileMapper,
            $this->staffProfileMapper,
            $this->relationshipProfileMapper,
        );
        $shadowTable->disable();
        $shadowTable->swapShadowWithBase();
    }
}
