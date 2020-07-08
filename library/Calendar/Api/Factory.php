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
     * @var array
     */
    private $allTypes = [];

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
        $this->databaseExternal     = $databaseExternal;
        $this->configLoader         = $config;

        $allConfigRecords = Calendar_Api_Config_Mapper::getInstance()->getAllConfigRecords();

        /* These configs are base64-encode()'d to get around a weird issue with unserialize
         * http://stackoverflow.com/questions/4264270/unserialize-sometimes-returns-false
         */
        foreach ($allConfigRecords as $currentConfig) {
            $config = $currentConfig->decodeConfig();

            try {
                if ($moduleChecker->isEnabled($config)) {
                    /* Update $config so it knows the calendar_connector id from whence it came
                     * This is used in produce() and passed to each unique/default implementation
                     */
                    $config->setId($currentConfig->getId());
                    $this->allTypes[] = $config;
                }
            } catch (\TypeError $e) {
                // I will not buy this record; it is scratched
            }
        }
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
     * @param int  $folderId
     * @param User $user
     * @param User $viewer
     * @return Calendar_Api_EventType_Interface
     */
    public function produceForFolderId(int $folderId, User $user, User $viewer): Calendar_Api_EventType_Interface
    {
        $calendarConfig = Calendar_Api_Config_Mapper::getInstance()->findOneForClass(
            Calendar_Api_EventType_Event_Schoolbox_Group::class
        );
        return new Calendar_Api_EventType_Event_Schoolbox_Group($calendarConfig->getId(), $user, $viewer, $folderId);
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
    private function produceFromConfig($config, User $user, User $viewer): Calendar_Api_EventType_Interface
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
                    new Visibility(Access::getInstance())
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
                    new Timetable_ColourManager(Timetable_ColourMapper::getInstance()->findAll())
                );
            case Calendar_Api_EventType_Timetable_ExternalConstrained_Default::class:
                return new Calendar_Api_EventType_Timetable_ExternalConstrained_Default($connectorId, $user, $viewer, $this->configLoader);
            case Calendar_Api_EventType_Timetable_Room::class:
                return new Calendar_Api_EventType_Timetable_Room($connectorId, $user, $viewer, new External_Timetable());
            case Calendar_Api_EventType_TimetableDays_External_Default::class:
                return new Calendar_Api_EventType_TimetableDays_External_Default($connectorId, $user, $viewer);
            default:
                throw new InvalidArgumentException("Calendar connector not recognised: $className");
        }
    }
}
