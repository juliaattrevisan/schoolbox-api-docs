<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Base\ShadowTable\Standard;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Base\ShadowTable\BaseEnable;
use Schoolbox\External\Event;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetablePeriodMapper;

/**
 * Enable writing to shadow tables (instead of to the base tables) for all SUDS
 * mappers, and copy the content of the base tables into the shadow tables.
 *
 * This includes mappers for:
 *
 * Classes and timetables:
 * * `sis_class`
 * * `sis_class_period`
 * * `sis_class_user`
 * * `sis_class_user_overrides`
 * * `sis_timetable_period`
 *
 * SIS events:
 * * `sis_event`
 * * `sis_event_campus`
 * * `sis_event_roletype`
 * * `sis_event_user`
 *
 * User profile tables:
 * * `sis_user_profile`
 * * `sis_user_profile_parent`
 * * `sis_user_profile_relation`
 * * `sis_user_profile_staff`
 * * `sis_user_profile_student`
 *
 * This does _not_ include mappers for:
 * * `sis_import_log`: this simply logs previous SIS import runs
 * * `sis_import_queue`: this simply stores scheduled SIS import runs
 * * `sis_timetable_cycle`: this is updated in a separate process
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
     * @param ClassEntryMapper                $classEntryMapper
     * @param ClassInvolvementMapper          $classInvolvementMapper
     * @param Event\Overrides\Mapper          $classInvolvementOverridesMapper
     * @param ClassPeriodMapper               $classPeriodMapper
     * @param TimetablePeriodMapper           $periodMapper
     * @param Event\Mapper                    $eventMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        UserProfile\Mapper $userProfileMapper,
        UserProfile\Student\Mapper $studentProfileMapper,
        UserProfile\Parent\Mapper $parentProfileMapper,
        UserProfile\Staff\Mapper $staffProfileMapper,
        UserProfile\Relationship\Mapper $relationshipProfileMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        Event\Overrides\Mapper $classInvolvementOverridesMapper,
        ClassPeriodMapper $classPeriodMapper,
        TimetablePeriodMapper $periodMapper,
        Event\Mapper $eventMapper,
    ) {
        parent::__construct(
            $log,
            $perform,
            $userProfileMapper,
            $studentProfileMapper,
            $parentProfileMapper,
            $staffProfileMapper,
            $relationshipProfileMapper,
            $classEntryMapper,
            $classInvolvementMapper,
            $classInvolvementOverridesMapper,
            $classPeriodMapper,
            $periodMapper,
            $eventMapper,
        );
    }
}
