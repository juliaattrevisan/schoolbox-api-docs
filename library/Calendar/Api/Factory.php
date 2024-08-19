<?php

/**
 * Calendar_Api_Factory
 *
 * Creates an API composite.
 *
 * Produce a stack of API classes both based on school domain and
 * data source. Uses the Composite pattern to treat an amalgamation
 * of APIs as a single API class.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Calendar\Api\ModuleChecker;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Alaress\Database\Connector\IConnector;
use Schoolbox\External\Connector\Factory as ExternalConnectorFactory;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Permission\Access\Access;
use Schoolbox\IConfigLoader;
use Schoolbox\User;

class Calendar_Api_Factory
{
    /**
     * A list of all the possible event types to display
     *
     * @var array|null
     */
    private ?array $allTypes = null;

    /**
     * A data source for assessments.
     *
     * @var Assessment\MapperProxy
     */
    private $assessmentDataSource;

    /** @var null|IConnector */
    private $databaseExternal;

    /** @var IConfigLoader */
    private $configLoader;

    /**
     * @var ModuleChecker
     */
    private ModuleChecker $moduleChecker;


    /**
     * Constructor
     *
     * @param Assessment\MapperProxy $assessmentDataSource
     * @param ModuleChecker          $moduleChecker
     * @param IConfigLoader          $config
     * @param null|IConnector        $databaseExternal
     */
    public function __construct(
        Assessment\MapperProxy $assessmentDataSource,
        ModuleChecker $moduleChecker,
        IConfigLoader $config,
        IConnector $databaseExternal = null
    ) {
        $this->assessmentDataSource = $assessmentDataSource;
        $this->databaseExternal = $databaseExternal;
        $this->configLoader = $config;
        $this->moduleChecker = $moduleChecker;

        $this->generateAvailableSubscriptions();
    }


    /**
     * Get a list of all the calendar subscriptions that were enabled
     *
     * @return array
     */
    public function generateAvailableSubscriptions(): array
    {
        /* Only compute this once
         */
        if (null === $this->allTypes) {
            $this->allTypes = [];

            $allConfigRecords = \Calendar_Api_Config_Mapper::getInstance()->getAllConfigRecords();

            foreach ($allConfigRecords as $currentConfig) {
                $currentConnectorClass = 'Calendar_Api_Config_Connector_' . $currentConfig->getConnectorClass();

                if ($this->moduleChecker->isEnabled($currentConnectorClass)) {
                    $this->allTypes[] = $currentConfig->decodeConfig();
                }
            }
        }

        return $this->allTypes;
    }


    /**
     * Produce an API Composite
     *
     * @param User $user
     * @param User $viewer
     * @return Calendar_Api_EventType_Interface
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function produceForUser(User $user, User $viewer): Calendar_Api_EventType_Interface
    {
        $compositeApi = new Calendar_Api_Composite();

        foreach ($this->allTypes as $config) {
            $compositeApi->add($this->produceFromConfig($config, $user, $viewer));
            // InvalidArgumentException or ReflectionException: NOT caught
        }

        return $compositeApi;
    }


    /**
     * Handles construction logic for a folder-specific variant of the calendar
     * connector.
     *
     * @param \Schoolbox\Resource\Folder\Model $folder
     * @param User                             $user
     * @param User                             $viewer
     * @return Calendar_Api_EventType_Interface
     */
    public function produceForFolder(\Schoolbox\Resource\Folder\Model $folder, User $user, User $viewer): Calendar_Api_EventType_Interface
    {
        $composite = new Calendar_Api_Composite();

        /* Group Events
         */
        $calendarConfig = Calendar_Api_Config_Mapper::getInstance()->findOneForClass(Calendar_Api_EventType_Event_Schoolbox_Group::class);

        $composite->add(new Calendar_Api_EventType_Event_Schoolbox_Group($calendarConfig->getId(), $user, $viewer, $folder->getId()));

        /* Summon the sis events that might be relevant to $folder, too
         */
        $sudsTimetableConfig = Calendar_Api_Config_Mapper::getInstance()->findOneForClass(Calendar_Api_EventType_SudsTimetable_Schoolbox_Default::class);

        // if folder code is set and using suds, get events from suds
        if ($sudsTimetableConfig->isActive() && $folder->getCodes() && $folder->getCodes()->count()) {
            $composite->add(new Calendar_Api_EventType_SudsTimetable_Schoolbox_Default(
                $sudsTimetableConfig->getId(),
                $user,
                $viewer,
                $folder->getCodes(),
                $this->configLoader,
                \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                \Schoolbox\External\ClassEntryMapper::getInstance(),
                \Schoolbox\External\ClassInvolvementMapper::getInstance(),
                \Schoolbox\External\Event\Mapper::getInstance(),
                \Schoolbox\External\Event\Overrides\Mapper::getInstance(),
                \Timetable_ColourMapper::getInstance(),
                \Schoolbox\External\Profile\Staff\Mapper::getInstance(),
                Access::getInstance(),
            ));
        }

        $sudsEventConfig = Calendar_Api_Config_Mapper::getInstance()->findOneForClass(Calendar_Api_EventType_SudsEvent_Schoolbox_Default::class);

        // if folder code is set and using suds, get events from suds
        if ($sudsEventConfig->isActive() && $folder->getCodes() && $folder->getCodes()->count()) {
            $composite->add(new Calendar_Api_EventType_SudsEvent_Schoolbox_Default(
                $sudsEventConfig->getId(),
                $user,
                $viewer,
                $folder->getCodes(),
                \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                \Schoolbox\External\Event\Mapper::getInstance(),
                \Schoolbox\External\Event\Campus\Mapper::getInstance(),
                \Schoolbox\External\Event\Roletype\Mapper::getInstance(),
                \Schoolbox\Resource\Folder\Mapper::getInstance()->disableCache()
            ));
        }

        return $composite;
    }


    /**
     * Only show the Timetable related subscriptions.
     * This is used specifically for the special Timetable Calendar representation
     *
     * This factory retrieves subscriptions whether or not they exist or are
     * enabled: this is because the timetable calendar shouldn't depend on the
     * standard calendar subscription status.
     *
     * @param User $user
     * @param User $viewer
     * @return Calendar_Api_EventType_Interface
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function produceForTimetableCalendar(User $user, User $viewer): Calendar_Api_EventType_Interface
    {
        $composite = new Calendar_Api_Composite();

        /* Combine all the timetable-related sources of data all together
         * This means you could potentially run both Schedule and Cyclic
         * timetable data sets concurrently. Wild.
         */
        $composite->add(
            new Calendar_Api_EventType_SudsTimetable_Schoolbox_Default(
                Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                $user,
                $viewer,
                null,
                $this->configLoader,
                \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                \Schoolbox\External\ClassEntryMapper::getInstance(),
                \Schoolbox\External\ClassInvolvementMapper::getInstance(),
                \Schoolbox\External\Event\Mapper::getInstance(),
                \Schoolbox\External\Event\Overrides\Mapper::getInstance(),
                \Timetable_ColourMapper::getInstance(),
                \Schoolbox\External\Profile\Staff\Mapper::getInstance(),
                Access::getInstance()
            )
        );

        $composite->add(
            new Calendar_Api_EventType_Timetable_ExternalConstrained_Default(
                Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                $user,
                $viewer,
                $this->configLoader,
                \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                Access::getInstance(),
            )
        );

        $composite->add(
            new Calendar_Api_EventType_TimetableDays_External_Default(
                Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                $user,
                $viewer,
                \Schoolbox\Calendar\EventTypeMapper::getInstance()
            )
        );

        return $composite;
    }


    /**
     * Handle unique construction logic for each variant of the calendar connector.
     *
     * @param Calendar_Api_Config_Record $config
     * @param User                       $user
     * @param User                       $viewer
     * @return Calendar_Api_EventType_Abstract
     * @throws InvalidArgumentException If the item can't be built
     * @throws UnexpectedValueException If unable to build the external event factory
     */
    public function produceFromConfig($config, User $user, User $viewer): Calendar_Api_EventType_Interface
    {
        $className   = "Calendar_Api_EventType_{$config->getEventType()}_{$config->getConnectorClass()}_Default";
        $connectorId = $config->getId();

        switch ($className) {
            case Calendar_Api_EventType_Custom_EOTC_Default::class:
                return new Calendar_Api_EventType_Custom_EOTC_Default($connectorId, $user, $viewer);
            case Calendar_Api_EventType_Event_External_Default::class:
                // Same as default constructor, but add the assessment data source
                $factory        = new ExternalConnectorFactory();
                $externalEvents = $factory->build($this->configLoader, $this->databaseExternal, ExternalConnectorFactory::EXTERNAL_EVENTS);
                return new Calendar_Api_EventType_Event_External_Default($connectorId, $user, $viewer, $externalEvents);
            case Calendar_Api_EventType_Event_Schoolbox_Campus::class:
                return new Calendar_Api_EventType_Event_Schoolbox_Campus($connectorId, $user, $viewer);
            case Calendar_Api_EventType_Event_Schoolbox_Default::class:
                return new Calendar_Api_EventType_Event_Schoolbox_Default($connectorId, $user, $viewer);
            case Calendar_Api_EventType_Event_Schoolbox_Group::class:
                return new Calendar_Api_EventType_Event_Schoolbox_Group($connectorId, $user, $viewer);
            case Calendar_Api_EventType_Event_Schoolbox_Personal::class:
                return new Calendar_Api_EventType_Event_Schoolbox_Personal($connectorId, $user, $viewer);
            case Calendar_Api_EventType_Event_Schoolbox_Type::class:
                return new Calendar_Api_EventType_Event_Schoolbox_Type($connectorId, $user, $viewer);
            case Calendar_Api_EventType_Event_Vcal_Default::class:
                return new Calendar_Api_EventType_Event_Vcal_Default($connectorId, $user, $viewer);
            case Calendar_Api_EventType_ResourceBooking_Booking_Default::class:
                return new Calendar_Api_EventType_ResourceBooking_Booking_Default($connectorId, $user, $viewer);
            case Calendar_Api_EventType_ResourceBooking_Moderation_Default::class:
                return new Calendar_Api_EventType_ResourceBooking_Moderation_Default($connectorId, $user, $viewer);
            case Calendar_Api_EventType_Submission_Schoolbox_Default::class:
                return new Calendar_Api_EventType_Submission_Schoolbox_Default(
                    $connectorId,
                    $user,
                    $viewer,
                    $this->assessmentDataSource,
                    new Visibility(Access::getInstance()),
                    \Schoolbox\Calendar\EventTypeMapper::getInstance()
                );

            /* These don't have connector configs
             */
            case Calendar_Api_EventType_ResourceBooking_Booking_ForCategory::class:
                return new Calendar_Api_EventType_ResourceBooking_Booking_ForCategory(Calendar_Api_Config_Mapper::CONFIGURATION_NONE, $user, $viewer);
            case Calendar_Api_EventType_ResourceBooking_Booking_ForItem::class:
                return new Calendar_Api_EventType_ResourceBooking_Booking_ForItem(Calendar_Api_Config_Mapper::CONFIGURATION_NONE, $user, $viewer);
            case Calendar_Api_EventType_ResourceBooking_Booking_ForUser::class:
                return new Calendar_Api_EventType_ResourceBooking_Booking_ForUser(Calendar_Api_Config_Mapper::CONFIGURATION_NONE, $user, $viewer);

            case Calendar_Api_EventType_Submission_Schoolbox_ForDueWork::class:
                return new Calendar_Api_EventType_Submission_Schoolbox_ForDueWork(
                    $connectorId,
                    $user,
                    $viewer,
                    $this->assessmentDataSource,
                    new Visibility(Access::getInstance()),
                    new Timetable_ColourManager(Timetable_ColourMapper::getInstance()->findAll()),
                    \Schoolbox\Calendar\EventTypeMapper::getInstance()
                );
            case Calendar_Api_EventType_Timetable_ExternalConstrained_Default::class:
                return new Calendar_Api_EventType_Timetable_ExternalConstrained_Default(
                    $connectorId,
                    $user,
                    $viewer,
                    $this->configLoader,
                    \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                    Access::getInstance(),
                );

            /* Resource Booking out of the cyclic grid
             * TODO (but where/how is this used/accessed?)
             */
            case Calendar_Api_EventType_Timetable_Room::class:
                return new Calendar_Api_EventType_Timetable_Room($connectorId, $user, $viewer, new External_Timetable());

            /* Resource Booking out of SUDS Timetable
             * TODO (but where/how is this used/accessed?)
             */
            case Calendar_Api_EventType_SudsTimetable_Room::class:
                return new Calendar_Api_EventType_SudsTimetable_Room($connectorId, $user, $viewer, \Schoolbox\External\Event\Mapper::getInstance());

            case Calendar_Api_EventType_TimetableDays_External_Default::class:
                return new Calendar_Api_EventType_TimetableDays_External_Default($connectorId, $user, $viewer, \Schoolbox\Calendar\EventTypeMapper::getInstance());
            case Calendar_Api_EventType_Fixtures_Schoolbox_Default::class:
                return new Calendar_Api_EventType_Fixtures_Schoolbox_Default(
                    $connectorId,
                    $user,
                    $viewer,
                    \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                    \Schoolbox\Fixtures\Matchup\Mapper::getInstance(),
                    \Schoolbox\Fixtures\Team\Player\Mapper::getInstance()
                );
            case Calendar_Api_EventType_SudsTimetable_Schoolbox_Default::class:
                return new Calendar_Api_EventType_SudsTimetable_Schoolbox_Default(
                    $connectorId,
                    $user,
                    $viewer,
                    null,
                    $this->configLoader,
                    \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                    \Schoolbox\External\ClassEntryMapper::getInstance(),
                    \Schoolbox\External\ClassInvolvementMapper::getInstance(),
                    \Schoolbox\External\Event\Mapper::getInstance(),
                    \Schoolbox\External\Event\Overrides\Mapper::getInstance(),
                    \Timetable_ColourMapper::getInstance(),
                    \Schoolbox\External\Profile\Staff\Mapper::getInstance(),
                    Access::getInstance(),
                );
            case Calendar_Api_EventType_SudsEvent_Schoolbox_Default::class:
                return new Calendar_Api_EventType_SudsEvent_Schoolbox_Default(
                    $connectorId,
                    $user,
                    $viewer,
                    null,
                    \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                    \Schoolbox\External\Event\Mapper::getInstance(),
                    \Schoolbox\External\Event\Campus\Mapper::getInstance(),
                    \Schoolbox\External\Event\Roletype\Mapper::getInstance(),
                    \Schoolbox\Resource\Folder\Mapper::getInstance()->disableCache()
                );
            default:
                throw new InvalidArgumentException("Calendar connector not recognised: $className");
        }
    }
}
