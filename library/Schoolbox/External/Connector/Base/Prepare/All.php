<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Base\Prepare;

use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Event;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper\ShadowTable;

/**
 * Prepares all SUDS mappers for writing to shadow tables, instead of to the
 * base tables.
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
class All implements IStrategy
{
    /**
     * Constructor
     *
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
     * @param Event\User\Mapper               $eventUserMapper
     * @param Event\Campus\Mapper             $eventCampusMapper
     * @param Event\Roletype\Mapper           $eventRoleTypeMapper
     */
    public function __construct(
        private UserProfile\Mapper $userProfileMapper,
        private UserProfile\Student\Mapper $studentProfileMapper,
        private UserProfile\Parent\Mapper $parentProfileMapper,
        private UserProfile\Staff\Mapper $staffProfileMapper,
        private UserProfile\Relationship\Mapper $relationshipProfileMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper,
        private Event\Overrides\Mapper $classInvolvementOverridesMapper,
        private ClassPeriodMapper $classPeriodMapper,
        private TimetablePeriodMapper $periodMapper,
        private Event\Mapper $eventMapper,
        private Event\User\Mapper $eventUserMapper,
        private Event\Campus\Mapper $eventCampusMapper,
        private Event\Roletype\Mapper $eventRoleTypeMapper,
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
            $this->classEntryMapper,
            $this->classInvolvementMapper,
            $this->classInvolvementOverridesMapper,
            $this->classPeriodMapper,
            $this->periodMapper,
            $this->eventMapper,
            $this->eventUserMapper,
            $this->eventCampusMapper,
            $this->eventRoleTypeMapper,
        );
        $shadowTable->createShadowTables();
        $shadowTable->enable();
    }
}
