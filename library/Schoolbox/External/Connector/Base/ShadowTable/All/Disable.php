<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Base\ShadowTable\All;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Base\ShadowTable\BaseDisable;
use Schoolbox\External\Event;
use Schoolbox\External\Profile;
use Schoolbox\External\Profile\Mapper;
use Schoolbox\External\TimetableCycleMapper;
use Schoolbox\External\TimetablePeriodMapper;

/**
 * Disable writing to shadow tables (instead of to the base tables) for all SUDS
 * related mappers. This includes mappers for:
 *
 * User profile:
 * * `sis_user_profile`
 * * `sis_user_profile_parent`
 * * `sis_user_profile_relation`
 * * `sis_user_profile_staff`
 * * `sis_user_profile_student`
 *
 * Timetable and timetabled classes:
 * * `sis_class`
 * * `sis_class_period`
 * * `sis_class_user`
 * * `sis_timetable_cycle`
 * * `sis_timetable_period`
 *
 * Calendar events and timetable overrides:
 * * `sis_class_user_overrides`
 * * `sis_event`
 * * `sis_event_campus`
 * * `sis_event_roletype`
 * * `sis_event_user`
 */
class Disable extends BaseDisable
{
    /**
     * Constructor
     *
     * @param LoggerInterface $log
     * @param bool $perform
     * @param string $version
     * @param Mapper $userProfileMapper
     * @param Profile\Student\Mapper $studentProfileMapper
     * @param Profile\Parent\Mapper $parentProfileMapper
     * @param Profile\Staff\Mapper $staffProfileMapper
     * @param Profile\Relationship\Mapper $relationshipProfileMapper
     * @param ClassEntryMapper $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param ClassPeriodMapper $classPeriodMapper
     * @param TimetableCycleMapper $cycleMapper
     * @param TimetablePeriodMapper $periodMapper
     * @param Event\Mapper $eventMapper
     * @param Event\Overrides\Mapper $classInvolvementOverridesMapper
     * @param Event\Roletype\Mapper $eventRoleTypeMapper
     * @param Event\Campus\Mapper $eventCampusMapper
     * @param Event\User\Mapper $eventUserMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        string $version,
        Profile\Mapper $userProfileMapper,
        Profile\Student\Mapper $studentProfileMapper,
        Profile\Parent\Mapper $parentProfileMapper,
        Profile\Staff\Mapper $staffProfileMapper,
        Profile\Relationship\Mapper $relationshipProfileMapper,
        ClassEntryMapper $classEntryMapper,
        ClassInvolvementMapper $classInvolvementMapper,
        ClassPeriodMapper $classPeriodMapper,
        TimetableCycleMapper $cycleMapper,
        TimetablePeriodMapper $periodMapper,
        Event\Mapper $eventMapper,
        Event\Overrides\Mapper $classInvolvementOverridesMapper,
        Event\Roletype\Mapper $eventRoleTypeMapper,
        Event\Campus\Mapper $eventCampusMapper,
        Event\User\Mapper $eventUserMapper,
    ) {
        parent::__construct(
            $log,
            $perform,
            $version,
            $userProfileMapper,
            $studentProfileMapper,
            $parentProfileMapper,
            $staffProfileMapper,
            $relationshipProfileMapper,
            $classEntryMapper,
            $classInvolvementMapper,
            $classPeriodMapper,
            $cycleMapper,
            $periodMapper,
            $eventMapper,
            $classInvolvementOverridesMapper,
            $eventRoleTypeMapper,
            $eventCampusMapper,
            $eventUserMapper,
        );
    }
}
