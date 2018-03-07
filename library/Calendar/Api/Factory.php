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
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Calendar\Api\ModuleChecker;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Alaress\Database\Connector\IConnector;
use Schoolbox\External\Connector\Factory as ExternalConnectorFactory;
use Schoolbox\Config;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Permission\Access\Access;

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


    /**
     * Constructor
     *
     * @param Assessment\MapperProxy $assessmentDataSource
     * @param ModuleChecker          $moduleChecker
     * @param null|IConnector        $databaseExternal
     */
    public function __construct(
        Assessment\MapperProxy $assessmentDataSource,
        ModuleChecker $moduleChecker,
        IConnector $databaseExternal = null
    ) {
        $this->assessmentDataSource = $assessmentDataSource;
        $this->databaseExternal     = $databaseExternal;

        $allConfigRecords = Calendar_Api_Config_Mapper::getInstance()->getAllConfigRecords();

        /* These configs are base64-encode()'d to get around a weird issue with unserialize
         * http://stackoverflow.com/questions/4264270/unserialize-sometimes-returns-false
         */
        foreach ($allConfigRecords as $currentConfig) {

            $config = $currentConfig->decodeConfig();

            if ($moduleChecker->isEnabled($config)) {
                /* Update $config so it knows the calendar_connector id from whence it came
                 * This is used in produce() and passed to each unique/default implementation
                 */
                $config->setId($currentConfig->getId());
                $this->allTypes[] = $config;
            }
        }
    }


    /**
     * Produce an API Composite
     *
     * @param \Schoolbox\User $user
     * @return Calendar_Api_Composite
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function produceForUser(\Schoolbox\User $user)
    {
        $compositeApi = new Calendar_Api_Composite();

        foreach ($this->allTypes as $config) {
            $compositeApi->add($this->produceFromConfig($config, $user));
            // InvalidArgumentException or ReflectionException: NOT caught
        }

        return $compositeApi;
    }


    /**
     * Handle unique construction logic for each variant of the calendar connector.
     *
     * @param Calendar_Api_Config_Record $config
     * @param \Schoolbox\User            $user
     * @return Calendar_Api_EventType_Abstract
     * @throws InvalidArgumentException If the item can't be built
     * @throws UnexpectedValueException If unable to build the external event factory
     */
    private function produceFromConfig($config, \Schoolbox\User $user): Calendar_Api_EventType_Interface
    {
        $className   = "Calendar_Api_EventType_{$config->getEventType()}_{$config->getConnectorClass()}_Default";
        $connectorId = $config->getId();

        switch ($className) {
            case Calendar_Api_EventType_Custom_EOTC_Default::class:
                return new Calendar_Api_EventType_Custom_EOTC_Default($connectorId, $user);
            case Calendar_Api_EventType_Event_External_Default::class:
                // Same as default constructor, but add the assessment data source
                $factory        = new ExternalConnectorFactory();
                $externalEvents = $factory->build(new Config\Loader(), $this->databaseExternal, ExternalConnectorFactory::EXTERNAL_EVENTS);
                return new Calendar_Api_EventType_Event_External_Default($connectorId, $user, $externalEvents);
            case Calendar_Api_EventType_Event_Schoolbox_Campus::class:
                return new Calendar_Api_EventType_Event_Schoolbox_Campus($connectorId, $user);
            case Calendar_Api_EventType_Event_Schoolbox_Default::class:
                return new Calendar_Api_EventType_Event_Schoolbox_Default($connectorId, $user);
            case Calendar_Api_EventType_Event_Schoolbox_Group::class:
                return new Calendar_Api_EventType_Event_Schoolbox_Group($connectorId, $user);
            case Calendar_Api_EventType_Event_Schoolbox_Personal::class:
                return new Calendar_Api_EventType_Event_Schoolbox_Personal($connectorId, $user);
            case Calendar_Api_EventType_Event_Schoolbox_Type::class:
                return new Calendar_Api_EventType_Event_Schoolbox_Type($connectorId, $user);
            case Calendar_Api_EventType_Event_Vcal_Default::class:
                return new Calendar_Api_EventType_Event_Vcal_Default($connectorId, $user);
            case Calendar_Api_EventType_ResourceBooking_Booking_Default::class:
                return new Calendar_Api_EventType_ResourceBooking_Booking_Default($connectorId, $user);
            case Calendar_Api_EventType_ResourceBooking_Booking_ForCategory::class:
                return new Calendar_Api_EventType_ResourceBooking_Booking_ForCategory($connectorId, $user);
            case Calendar_Api_EventType_ResourceBooking_Booking_ForItem::class:
                return new Calendar_Api_EventType_ResourceBooking_Booking_ForItem($connectorId, $user);
            case Calendar_Api_EventType_ResourceBooking_Booking_ForUser::class:
                return new Calendar_Api_EventType_ResourceBooking_Booking_ForUser($connectorId, $user);
            case Calendar_Api_EventType_ResourceBooking_Moderation_Default::class:
                return new Calendar_Api_EventType_ResourceBooking_Moderation_Default($connectorId, $user);
            case Calendar_Api_EventType_Submission_Schoolbox_Default::class:
                return new Calendar_Api_EventType_Submission_Schoolbox_Default(
                    $connectorId,
                    $user,
                    $this->assessmentDataSource,
                    new Visibility(Access::getInstance())
                );
            case Calendar_Api_EventType_Submission_Schoolbox_ForDueWork::class:
                return new Calendar_Api_EventType_Submission_Schoolbox_ForDueWork(
                    $connectorId,
                    $user,
                    $this->assessmentDataSource,
                    new Visibility(Access::getInstance()),
                    new Timetable_ColourManager(Timetable_ColourMapper::getInstance()->findAll())
                );
            case Calendar_Api_EventType_Timetable_ExternalConstrained_Default::class:
                return new Calendar_Api_EventType_Timetable_ExternalConstrained_Default($connectorId, $user);
            case Calendar_Api_EventType_Timetable_Room::class:
                return new Calendar_Api_EventType_Timetable_Room($connectorId, $user, new External_Timetable());
            case Calendar_Api_EventType_TimetableDays_External_Default::class:
                return new Calendar_Api_EventType_TimetableDays_External_Default($connectorId, $user);
            default:
                throw new InvalidArgumentException("Calendar connector not recognised: $className");
        }
    }
}
