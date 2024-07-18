<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Base\ShadowTable\Timetable;

use Psr\Log\LoggerInterface;
use Schoolbox\External\ClassEntryMapper;
use Schoolbox\External\ClassInvolvementMapper;
use Schoolbox\External\ClassPeriodMapper;
use Schoolbox\External\Connector\Base\ShadowTable\BaseDisable;
use Schoolbox\External\Event;
use Schoolbox\External\TimetablePeriodMapper;

/**
 * Disable writing to shadow tables (instead of to the base tables) for SUDS
 * timetable mappers.
 *
 * This includes mappers for:
 * * `sis_class`
 * * `sis_class_period`
 * * `sis_class_user`
 * * `sis_class_user_overrides`
 * * `sis_event`
 * * `sis_timetable_period`
 *
 * This does _not_ include the mapper for `sis_timetable_cycle`: that table's
 * data is updated in a separate process.
 */
class Disable extends BaseDisable
{
    /**
     * Constructor
     *
     * @param LoggerInterface        $log
     * @param bool                   $perform
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param Event\Overrides\Mapper $classInvolvementOverridesMapper
     * @param ClassPeriodMapper      $classPeriodMapper
     * @param TimetablePeriodMapper  $periodMapper
     * @param Event\Mapper           $eventMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
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
            $classEntryMapper,
            $classInvolvementMapper,
            $classInvolvementOverridesMapper,
            $classPeriodMapper,
            $periodMapper,
            $eventMapper,
        );
    }
}
