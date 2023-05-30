<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Calendar\Event\Collection;
use Calendar\Event\Type\EventInterface;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\UserAccess;

/**
 * Fulfils the Dashboard Timetable component's requirements
 * when using a SUDS Timetable connector
 */
class Component_Dashboard_Timetable_SudsStrategy implements Component_Dashboard_Timetable_ComponentInterface
{
    /**
     * A set of sis_event records via the calendar subscription mechanism
     *
     * @var GenericCollection<EventInterface>
     */
    private GenericCollection $events;


    /**
     * A list of (code, url) tuples to see if you had access to a folder
     * so we can give users a link in the ui
     *
     * @var array
     */
    private array $folderAccess = [];


    /**
     * Just raid the subscription for data.
     * ez
     *
     * @param Component_Instance                         $componentInstance
     * @param \Schoolbox\User                            $user
     * @param \Schoolbox\IConfigLoader                   $configLoader
     * @param \Schoolbox\Permission\Access\Access        $access
     * @param \Schoolbox\Resource\Folder\Mapper          $folderMapper
     * @param \Schoolbox\Calendar\EventTypeMapper        $eventTypeMapper
     * @param \Schoolbox\External\ClassEntryMapper       $classEntryMapper
     * @param \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper
     * @param \Schoolbox\External\Event\Mapper           $eventMapper
     * @param \Schoolbox\External\Event\Overrides\Mapper $overridesMapper
     * @param Timetable_ColourMapper                     $colourMapper
     * @param \Schoolbox\External\Profile\Staff\Mapper   $staffProfileMapper
     */
    public function __construct(
        private Component_Instance $componentInstance,
        private \Schoolbox\User $user,
        private \Schoolbox\IConfigLoader $configLoader,
        private \Schoolbox\Permission\Access\Access $access,
        private \Schoolbox\Resource\Folder\Mapper $folderMapper,
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
     * @throws LogicException
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function execute(): bool
    {
        $startTimestamp = \Alaress_Date_Format::now()->startOfDay()->getUnixTimeStamp();
        $endTimestamp = \Alaress_Date_Format::now()->endOfDay()->getUnixTimeStamp();

        $this->events = (new Calendar_Api_EventType_SudsTimetable_Schoolbox_Default(
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

        /* Sort by time, because this is a ordered grid view, not a fullcalendar implementation
         */
        $this->events->sort();

        /* Links to homepages are for some reason different, depending on where you are
         * The event modal does it differently to this component
         *
         * Just build the lookup independently for now, for quick reference.
         *
         * This reimplements a similar behaviour from CyclicStrategy's buildClassContext() method
         * It's not very efficient, but on the less-dark side, there shouldn't be an
         * insane amount of data here
         */
        $folderLookup = [];

        /** @var \Calendar\Event\Type\SudsTimetable\Event $event */
        foreach ($this->events as $event) {
            try {
                if (!isset($folderLookup[$event->getCode()])) {
                    $folder = $this->folderMapper->findByCode($event->getCode());

                    $folderLookup[$event->getCode()] = $folder;
                } else {
                    $folder = $folderLookup[$event->getCode()];
                }

                /* But...this always returns ACCESS_WRITE even if I set permissions to None
                 * in the Modify Folder screen, and remove the Homepage Template, and set
                 * Create Group to off. Staff users seem to always have WRITE access from
                 * somewhere, and I can't do anything about that?
                 */
                if ($this->access->getFolderAccess($this->user, $folder->getId()) >= UserAccess::ACCESS_READ) {
                    /* SUDS Timetable has no usage for campuses, so we can hardcode the homepage
                     * url in the ui instead of generating it here. I haven't seen any crazy
                     * class codes like Synergetic's url-unsafe character set.
                     *
                     * Until that happens we can just a bool hash lookup
                     */
                    $this->folderAccess[$event->getCode()] = true;
                }
            } catch (\Schoolbox\Mapper\Exception $e) {
                /* noop
                 *
                 * Something went wrong with finding folders, so the default
                 * is to not provide a link until that gets sorted out
                 */
            }
        }

        return true;
    }


    /**
     * @param string $templateHandle
     * @return bool|array
     */
    public function renderTemplate(string $templateHandle): bool|array
    {
        if ($this->events->count() < 1) {
            return false;
        }

        return [
            'template' => '@timetable/Dashboard/sudsComponent.twig',
            'data' => [
                'componentInstanceId' => $this->componentInstance->getId(),
                'now' => \Alaress_Date_Format::now()->getUnixTimeStamp(),
                'events' => $this->events,
                'folderAccess' => $this->folderAccess
            ]
        ];
    }
}
