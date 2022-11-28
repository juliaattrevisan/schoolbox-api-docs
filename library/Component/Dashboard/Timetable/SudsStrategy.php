<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

/**
 * Fulfils the Dashboard Timetable component's requirements
 * when using a SUDS Timetable connector
 */
class Component_Dashboard_Timetable_SudsStrategy implements Component_Dashboard_Timetable_ComponentInterface
{
    /**
     * Just raid the subscription for data.
     * ez
     *
     * @param \Schoolbox\User                            $user
     * @param \Schoolbox\IConfigLoader                   $configLoader
     * @param \Schoolbox\Calendar\EventTypeMapper        $eventTypeMapper
     * @param \Schoolbox\External\ClassEntryMapper       $classEntryMapper
     * @param \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper
     * @param \Schoolbox\External\Event\Mapper           $eventMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     * @param Timetable_ColourMapper                     $colourMapper
     * @param \Schoolbox\External\Profile\Staff\Mapper   $staffProfileMapper
     */
    public function __construct(
        private \Schoolbox\User $user,
        private \Schoolbox\IConfigLoader $configLoader,
        private \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper,
        private \Schoolbox\External\ClassEntryMapper $classEntryMapper,
        private \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper,
        private \Schoolbox\External\Event\Mapper $eventMapper,
        private \Schoolbox\External\Event\Overrides\Mapper $overridesMapper,
        private \Timetable_ColourMapper $colourMapper,
        private \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper
    ) {
    }


    /**
     * @return bool
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws \DebugBar\DebugBarException
     */
    public function execute(): bool
    {
        $startTimestamp = \Alaress_Date_Format::now()->startOfDay()->getUnixTimeStamp();
        $endTimestamp = \Alaress_Date_Format::now()->endOfDay()->getUnixTimeStamp();

        $events = (new Calendar_Api_EventType_SudsTimetable_Schoolbox_Default(
            Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
            $this->user,
            $this->user,
            null,
            $this->configLoader,
            $this->eventTypeMapper,
            $this->classEntryMapper,
            $this->classInvolvementMapper,
            $this->eventMapper,
            $this->overridesMapper,
            $this->colourMapper,
            $this->staffProfileMapper
        ))->getEvents($startTimestamp, $endTimestamp);

        new \Alaress_Probe($events);
    }


    /**
     * @param string $templateHandle
     * @return bool
     */
    public function renderTemplate(string $templateHandle): bool
    {
        // TODO: Implement renderTemplate() method.
    }
}
