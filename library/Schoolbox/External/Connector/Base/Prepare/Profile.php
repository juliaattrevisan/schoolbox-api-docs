<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Base\Prepare;

use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper\ShadowTable;

/**
 * Prepares the profile mappers for writing to shadow tables, instead of to the
 * base tables.
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
        $shadowTable->createShadowTables();
        $shadowTable->enable();
    }
}
