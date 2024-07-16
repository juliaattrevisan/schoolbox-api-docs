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
use Schoolbox\External\TimetablePeriodMapper;
use Schoolbox\Mapper\ShadowTable;

/**
 * Prepares the timetable mappers for writing to shadow tables, instead of to
 * the base tables.
 */
class Timetable implements IStrategy
{
    /**
     * Constructor
     *
     * @param Event\Mapper           $eventMapper
     * @param ClassEntryMapper       $classEntryMapper
     * @param ClassInvolvementMapper $classInvolvementMapper
     * @param ClassPeriodMapper      $classPeriodMapper
     * @param TimetablePeriodMapper  $periodMapper
     * @param Event\Overrides\Mapper $overridesMapper
     */
    public function __construct(
        private Event\Mapper $eventMapper,
        private ClassEntryMapper $classEntryMapper,
        private ClassInvolvementMapper $classInvolvementMapper,
        private ClassPeriodMapper $classPeriodMapper,
        private TimetablePeriodMapper $periodMapper,
        private Event\Overrides\Mapper $overridesMapper
    ) {
    }

    /**
     * @inheritdoc
     */
    public function import(): void
    {
        $shadowTable = new ShadowTable(
            $this->eventMapper,
            $this->classEntryMapper,
            $this->classInvolvementMapper,
            $this->classPeriodMapper,
            $this->periodMapper,
            $this->overridesMapper,
        );
        $shadowTable->createShadowTables();
        $shadowTable->enable();
    }
}
