<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Base\ShadowTable\Profile;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Base\ShadowTable\BaseEnable;
use Schoolbox\External\Profile as UserProfile;

/**
 * Enable writing to shadow tables (instead of to the base tables) for all SUDS
 * profile mappers.
 *
 * This includes mappers for:
 * * `sis_user_profile`
 * * `sis_user_profile_parent`
 * * `sis_user_profile_relation`
 * * `sis_user_profile_staff`
 * * `sis_user_profile_student`
 */
class Enable extends BaseEnable
{
    /**
     * Constructor
     *
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param UserProfile\Mapper              $userProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipProfileMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        UserProfile\Mapper $userProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipProfileMapper,
    ) {
        parent::__construct(
            $log,
            $perform,
            $userProfileMapper,
            $studentProfileMapper,
            $parentProfileMapper,
            $staffProfileMapper,
            $relationshipProfileMapper,
        );
    }
}
