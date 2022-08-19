<?php

/**
 * Updated calendar exporter
 *
 * Now supports more types of export than just all events at once, and supports
 * export for events for a user, given a token.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Calendar\Event\Collection\CSVExporter;
use Calendar\Event\Collection\JsonExporter;
use Calendar\Event\Collection\Renderer;
use Calendar\Event\Collection\VCalExporter;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Collection;
use Schoolbox\External\Connector\Factory as ExternalConnectorFactory;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\ResourceBooking\Item\Mapper as ItemMapper;
use Schoolbox\ResourceBooking\Report\CalendarApiFactory;
use Schoolbox\UserMapper;
use Crell\ApiProblem\ApiProblem;
use Sentry\State\HubInterface;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

// This may take a while
SitePage::extendExecutionTime();

/**
 * Returns the config id for the specified calendar connector.
 *
 * @param string $className
 * @return Calendar_Api_Config_Record
 * @throws InvalidArgumentException If `$className` isn't a `Calendar_Api_EventType_Interface`
 * @throws UnexpectedValueException If connector is not configured
 */
function getConnector(string $className)
{
    // Must actually be a calendar class we're requesting
    if (!is_a($className, Calendar_Api_EventType_Interface::class, true)) {
        throw new InvalidArgumentException(
            sprintf(
                'Calendar connector class does not exist, or is not a %s: %s',
                Calendar_Api_EventType_Interface::class,
                $className
            )
        );
    }

    try {
        $connectorConfig = Calendar_Api_Config_Mapper::getInstance()->findOneForClass($className);
    } catch (\Schoolbox\Mapper\Exception $e) {
        throw new UnexpectedValueException(
            "Calendar connector configuration record is missing: $className",
            $e->getCode(),
            $e
        );
    }

    return $connectorConfig;
}

/**
 * Returns configs for all VCal calendar connectors.
 *
 * @param string $className
 * @return Calendar_Api_Config_Record[]|Collection
 * @throws InvalidArgumentException If `$className` isn't a `Calendar_Api_EventType_Interface`
 */
function getVCalConfigs(string $className): Collection|array
{
    // Must actually be a calendar class we're requesting.
    if (!is_a($className, Calendar_Api_EventType_Interface::class, true)) {
        throw new InvalidArgumentException(
            sprintf(
                'Calendar connector class does not exist, or is not a %s: %s',
                Calendar_Api_EventType_Interface::class,
                $className
            )
        );
    }

    return Calendar_Api_Config_Mapper::getInstance()->getAllConfigRecords()->filterBy('connectorClass', 'Vcal');
}

/**
 * Builds an API class.
 *
 * @todo move this to a factory class; but there already is one, Calendar_Api_Factory
 *
 * @param string            $class   The class to build
 * @param array             $params  Params to pass into its constructor
 * @param bool              $enabled Is this class enabled? 404 if not
 * @param null|HubInterface $sentry  If something goes wrong, report it here
 * @return Calendar_Api_EventType_Interface
 * @throws ReflectionException if $class doesn't exist
 */
function buildApiClass(string $class, array $params, bool $enabled, ?HubInterface $sentry): Calendar_Api_EventType_Interface
{
    if (!$enabled) {
        // Not enabled; this is fine
        return new Calendar_Api_Composite();
    }

    try {
        $configuration = getConnector($class);
    } catch (UnexpectedValueException $e) {
        // Config missing: this is fine
        return new Calendar_Api_Composite();
    } catch (InvalidArgumentException $e) {
        // Invalid class: this is not fine, log to Sentry
        if ($sentry) {
            $sentry->captureException($e);
        }
        return new Calendar_Api_Composite();
    }

    if ($configuration->isActive()) {
        // Active: build API class
        $constructorParams = array_merge([$configuration->getId()], $params);
        $reflectionClass   = new ReflectionClass($class);
        return $reflectionClass->newInstanceArgs($constructorParams);
    }

    // Inactive: this is fine
    return new Calendar_Api_Composite();
}

/**
 * Builds an API class given a VCal connector
 *
 * @param Calendar_Api_Config_Record $vcalConfig
 * @param string                     $class  The class to build
 * @param array                      $params Params to pass into its constructor
 * @return Calendar_Api_EventType_Interface
 * @throws ReflectionException if $class doesn't exist
 */
function buildApiClassFromVCalConfig(Calendar_Api_Config_Record $vcalConfig, string $class, array $params): Calendar_Api_EventType_Interface
{
    if ($vcalConfig->isActive()) {
        // Active: build API class
        $constructorParams = array_merge([$vcalConfig->getId()], $params);
        $reflectionClass   = new ReflectionClass($class);
        return $reflectionClass->newInstanceArgs($constructorParams);
    }

    // Inactive: this is fine
    return new Calendar_Api_Composite();
}

// Always output here
switch ($_GET['format'] ?? '') {
    case 'csv':
        $exporter = new CSVExporter();
        break;
    case 'json':
        $exporter = new JsonExporter();
        break;
    default:
        $exporter = new VCalExporter();
}

/* The export mechanism determines the way you display errors
 * vcal and json should just http_response_code() whatever their problems are
 * because they're accessed from some other app
 *
 * csv, however, should tell the user what went wrong
 */
try {
    // Actually build the export
    $token              = $_GET['token'];
    $export_type        = $_GET['export'] ?? null;
    $filename           = 'calendar';
    $resourceId         = (int)($_GET['resource_id'] ?? 0);
    $resourceCategoryId = (int)($_GET['resource_category_id'] ?? 0);
    $eventTypeId        = (int)($_GET['event_type'] ?? 0);
    if ($resourceId > 0) {
        $export_type = 'resource';
    }
    if ($resourceCategoryId > 0) {
        $export_type = 'resource_category';
    }
    if (empty($token)) {
        throw new SchoolboxException('No token provided', 401);
    }

    //
    // Not 100% happy with this token but meh, it's what's being used in other
    // places that need a token.
    try {
        //TODO: fix hydration so we only need one call
        $user = UserMapper::getInstance()->findOneByColumn('key', $token);
        // Rehydrate with campus collection
        $user = UserMapper::getInstance()->findById($user->getId());
    } catch (Exception $ex) {
        throw new SchoolboxException('Invalid token', 401);
    }
    $viewer = $user;

    /** @var IConfigLoader $config */
    $config            = $kernel->get('config');
    $back              = (int)$config->get('calendar_export_start');
    $forward           = (int)$config->get('calendar_export_end');
    $timetable_forward = (int)$config->get('timetable_export_end');

    $back              = $back ? abs($back) : 1;
    $forward           = $forward ? abs($forward) : 12;
    $timetable_forward = $timetable_forward ? abs($timetable_forward) : 3;

    // Enable override of start/end dates
    if (isset($_GET['start'])) {
        $startTimestamp = \strtotime($_GET['start']);
    }
    if (isset($_GET['end'])) {
        $endTimestamp = \strtotime($_GET['end']);
        // Always assume end of day
        $endTimestamp = Alaress_Date_Format::newFromTimestamp($endTimestamp)->endOfDay()->getUnixTimeStamp();
    }
    // Default start and end times
    $startTimestamp = $startTimestamp ?? \strtotime("-{$back} months", $_SERVER['REQUEST_TIME']);
    $endTimestamp   = $endTimestamp ?? \strtotime("+{$forward} months", $_SERVER['REQUEST_TIME']);

    // Ensure the requested period is not greater than what is configured in the admin so students don't bring down server
    if (isset($_GET['start']) || isset($_GET['end'])) {
        $permittedPeriodMonths = $back + $forward;
        $requestedPeriodMonths = (int)(($endTimestamp - $startTimestamp) / (3600 * 24 * 31));
        if ($requestedPeriodMonths > $permittedPeriodMonths) {
            $message = "Requested period is {$requestedPeriodMonths} months, but permitted period is {$permittedPeriodMonths} months";
            \http_response_code(401);

            $problem = new ApiProblem('You requested a period of time that is longer than permitted');
            $problem->setDetail($message);

            $format = $_GET['format'] ?? '';
            switch (\strtolower($format)) {
                case 'json':
                    echo $problem->asJson();
                    break;
                case 'csv':
                default:
                    echo $message;
            }
            exit;
        }
    }

    // Report errors in this thing
    /** @var HubInterface|null $sentry */
    $sentry = $kernel->getDependencyInjector()->has('sentry')
        ? $kernel->getDependencyInjector()->get('sentry')
        : null;

    switch ($export_type) {
        case 'personal':
            $api = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Personal::class,
                [ $user, $viewer ],
                true,
                $sentry
            );

            $filename = 'personal';
            break;

        case 'group':
            $export_id = (int)$_GET['group'];

            /* Check if $_GET['group'] is a meaningful value before event
             * trying to find a folder
             */
            if ($export_id <= 0) {
                throw new SchoolboxException('Please select a group', 400);
            }

            $folder = \Schoolbox\Resource\Folder\Mapper::getInstance()->findById($export_id);

            $api = new Calendar_Api_Composite();

            $api->add(buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Group::class,
                [ $user, $viewer, $export_id ],
                true,
                $sentry
            ));

            /* SUDS Timetable
             */
            $api->add(buildApiClass(
                Calendar_Api_EventType_SudsTimetable_Schoolbox_Default::class,
                [
                    $user,
                    $viewer,
                    $folder->getCodes(),
                    \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                    \Schoolbox\External\ClassEntryMapper::getInstance(),
                    \Schoolbox\External\ClassInvolvementMapper::getInstance(),
                    \Schoolbox\External\Event\Mapper::getInstance(),
                    \Schoolbox\External\Event\Overrides\Mapper::getInstance(),
                    \Schoolbox\UserMapper::getInstance(),
                    \Timetable_ColourMapper::getInstance()
                ],
                true,
                $sentry
            ));

            /* SUDS Event
             */
            $api->add(buildApiClass(
                Calendar_Api_EventType_SudsEvent_Schoolbox_Default::class,
                [
                    $user,
                    $viewer,
                    $folder->getCodes(),
                    \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                    \Schoolbox\External\Event\Mapper::getInstance(),
                    \Schoolbox\External\Event\Campus\Mapper::getInstance(),
                    \Schoolbox\External\Event\Roletype\Mapper::getInstance()
                ],
                true,
                $sentry
            ));

            $filename = sprintf('group%d', $export_id);
            break;

        case 'campus':
            $export_id = (int)$_GET['campus'];
            if ($export_id <= 0) {
                throw new SchoolboxException('Please select a campus', 400);
            }

            $api = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Campus::class,
                [ $user, $viewer, $export_id ],
                true,
                $sentry
            );

            $filename = sprintf('campus%d', $export_id);
            break;

        case 'duework':
            /** @var MapperProxy $assessmentDataSource */
            $assessmentDataSource = $kernel->get('assessmentDataSource');
            $visibility           = new Visibility(Access::getInstance());

            $api = buildApiClass(
                Calendar_Api_EventType_Submission_Schoolbox_Default::class,
                [ $user, $viewer, $assessmentDataSource, $visibility, \Schoolbox\Calendar\EventTypeMapper::getInstance() ],
                true,
                $sentry
            );

            $filename = 'duework';
            break;

        case 'timetable':
            $api = buildApiClass(
                Calendar_Api_EventType_Timetable_ExternalConstrained_Default::class,
                [ $user, $viewer, $kernel->getDependencyInjector()->get('config'), \Schoolbox\Calendar\EventTypeMapper::getInstance() ],
                true,
                $sentry
            );

            $filename       = 'timetable';
            $startTimestamp = $_SERVER['REQUEST_TIME'];
            $endTimestamp   = strtotime("+{$timetable_forward} months", $_SERVER['REQUEST_TIME']);
            break;

        case 'timetabledays':
            $api = buildApiClass(
                Calendar_Api_EventType_TimetableDays_External_Default::class,
                [ $user, $viewer, \Schoolbox\Calendar\EventTypeMapper::getInstance() ],
                true,
                $sentry
            );

            $filename       = 'timetabledays';
            $startTimestamp = $_SERVER['REQUEST_TIME'];
            $endTimestamp   = strtotime("+{$timetable_forward} months", $_SERVER['REQUEST_TIME']);
            break;

        case 'resources':
            /* Only include this api if Resource Booking is enabled by Feature Flag
             */
            $api = buildApiClass(
                Calendar_Api_EventType_ResourceBooking_Booking_Default::class,
                [ $user, $viewer ],
                (bool)$config->get('resource_booking_enabled'),
                $sentry
            );

            $filename = 'resources';
            break;

        case 'resource':
            $api = new Calendar_Api_Composite();

            /* Only include this api if Resource Booking is enabled by Feature Flag
             */
            if ($config->get('resource_booking_enabled')) {
                $item     = ItemMapper::getInstance()->findById($resourceId);
                $roomCode = trim($item->getRoomCode());

                // Add the resource API
                $resourceApi = new Calendar_Api_EventType_ResourceBooking_Booking_ForItem(
                    Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                    $user,
                    $user,
                    [ $resourceId ]
                );

                $api->add($resourceApi);

                // If room code is set, get timetable for the room
                if (!empty($roomCode)) {
                    $timetableApi = new Calendar_Api_EventType_Timetable_Room(
                        Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                        $user,
                        $user,
                        $kernel->getDependencyInjector()->get('externalTimetable'),
                        [ $roomCode => $item->getId() ]
                    );

                    $api->add($timetableApi);
                }
            }

            $endTimestamp = strtotime("+{$timetable_forward} months", $_SERVER['REQUEST_TIME']);

            $filename = sprintf('resource%d', $resourceId);
            break;

        case 'resource_category':
            /* Only include this api if Resource Booking is enabled by Feature Flag
             */
            if ($config->get('resource_booking_enabled')) {
                $apiFactory = new CalendarApiFactory($user, $kernel->getDependencyInjector()->get('externalTimetable'));
                $api        = $apiFactory->buildFromResourceCollection(
                    ItemMapper::getInstance()->findByColumn(
                        'resource_category_id',
                        $resourceCategoryId,
                        [ 'order' => [ 'name' => 'asc', ] ]
                    )
                );

                $endTimestamp = strtotime("+{$timetable_forward} months", $_SERVER['REQUEST_TIME']);
            } else {
                $api = new Calendar_Api_Composite();
            }

            $filename = sprintf('resourceCategory%d', $resourceCategoryId);
            break;

        case 'event_type':
            /* Check if $_GET['group'] is a meaningful value before event
             * trying to find a folder
             */
            if ($eventTypeId <= 0) {
                throw new SchoolboxException('Please select an event type', 400);
            }

            $api = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Type::class,
                [ $user, $viewer, $eventTypeId ],
                true,
                $sentry
            );

            $filename = sprintf('eventType%d', $eventTypeId);
            break;

        case 'external':
            /** @var IConnector $databaseExternal */
            $databaseExternal = $kernel->getDependencyInjector()->get('databaseExternal');
            $factory          = new ExternalConnectorFactory();
            $externalEvents   = $factory->build(
                $kernel->getDependencyInjector()->get('config'),
                $databaseExternal,
                ExternalConnectorFactory::EXTERNAL_EVENTS
            );

            $api = new Calendar_Api_Composite();

            $api->add(buildApiClass(
                Calendar_Api_EventType_Event_External_Default::class,
                [ $user, $viewer, $externalEvents ],
                true,
                $sentry
            ));

            /* Fixtures
             */
            $api->add(buildApiClass(
                Calendar_Api_EventType_Fixtures_Schoolbox_Default::class,
                [
                    $user,
                    $viewer,
                    \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                    \Schoolbox\Fixtures\Matchup\Mapper::getInstance(),
                    \Schoolbox\Fixtures\Team\Player\Mapper::getInstance()
                ],
                true,
                $sentry
            ));

            /* SUDS Timetable
             */
            $api->add(buildApiClass(
                Calendar_Api_EventType_SudsTimetable_Schoolbox_Default::class,
                [
                    $user,
                    $viewer,
                    null,
                    \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                    \Schoolbox\External\ClassEntryMapper::getInstance(),
                    \Schoolbox\External\ClassInvolvementMapper::getInstance(),
                    \Schoolbox\External\Event\Mapper::getInstance(),
                    \Schoolbox\External\Event\Overrides\Mapper::getInstance(),
                    \Schoolbox\UserMapper::getInstance(),
                    \Timetable_ColourMapper::getInstance()
                ],
                true,
                $sentry
            ));

            /* SUDS Event
             */
            $api->add(buildApiClass(
                Calendar_Api_EventType_SudsEvent_Schoolbox_Default::class,
                [
                    $user,
                    $viewer,
                    null,
                    \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                    \Schoolbox\External\Event\Mapper::getInstance(),
                    \Schoolbox\External\Event\Campus\Mapper::getInstance(),
                    \Schoolbox\External\Event\Roletype\Mapper::getInstance()
                ],
                true,
                $sentry
            ));

            $filename = 'external';
            break;

        /* All EOTC Events
         */
        case 'eotc_all':
            $api = buildApiClass(
                Calendar_Api_EventType_Custom_EOTC_Default::class,
                [ $user, $viewer ],
                (bool)$config->get('eotc_enabled'),
                $sentry
            );

            break;
        case 'all':
        case 'all_external':
            // All day events, but exclude other sources
            $api    = new Calendar_Api_Composite();
            $subApi = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Default::class,
                [ $user, $viewer ],
                true,
                $sentry
            );
            $api->add($subApi);

            // Only include these api if Resource Booking is enabled by Feature Flag
            $resourceBookingEnabled = (bool)$config->get('resource_booking_enabled');
            if ($resourceBookingEnabled) {
                $subApi = buildApiClass(
                    Calendar_Api_EventType_ResourceBooking_Booking_Default::class,
                    [ $user, $viewer ],
                    true,
                    $sentry
                );
                $api->add($subApi);
                $subApi = buildApiClass(
                    Calendar_Api_EventType_ResourceBooking_Moderation_Default::class,
                    [ $user, $viewer ],
                    true,
                    $sentry
                );
                $api->add($subApi);
            }

            // Only include EOTC if enabled
            $eotcEnabled = (bool)$config->get('eotc_enabled');
            if ($eotcEnabled) {
                $subApi = buildApiClass(
                    Calendar_Api_EventType_Custom_EOTC_Default::class,
                    [ $user, $viewer ],
                    true,
                    $sentry
                );
                $api->add($subApi);
            }

            //if including timetable classes, do that now
            if ('all_external' === $export_type) {
                //build timetable
                $subApi = buildApiClass(
                    Calendar_Api_EventType_Timetable_ExternalConstrained_Default::class,
                    [ $user, $viewer, $kernel->getDependencyInjector()->get('config'), \Schoolbox\Calendar\EventTypeMapper::getInstance() ],
                    true,
                    $sentry
                );
                $api->add($subApi);

                //build external events
                /** @var IConnector $databaseExternal */
                $databaseExternal = $kernel->getDependencyInjector()->get('databaseExternal');
                $factory          = new ExternalConnectorFactory();
                $externalEvents   = $factory->build(
                    $kernel->getDependencyInjector()->get('config'),
                    $databaseExternal,
                    ExternalConnectorFactory::EXTERNAL_EVENTS
                );

                $subApi = buildApiClass(
                    Calendar_Api_EventType_Event_External_Default::class,
                    [ $user, $viewer, $externalEvents ],
                    true,
                    $sentry
                );
                $api->add($subApi);

                //build vcal sources
                foreach (getVCalConfigs(Calendar_Api_EventType_Event_Vcal_Default::class) as $connector) {
                    $subApi = buildApiClassFromVCalConfig(
                        $connector,
                        Calendar_Api_EventType_Event_Vcal_Default::class,
                        [ $user, $viewer ]
                    );
                    $api->add($subApi);
                }

                /* Fixtures
                 */
                $api->add(buildApiClass(
                    Calendar_Api_EventType_Fixtures_Schoolbox_Default::class,
                    [
                        $user,
                        $viewer,
                        \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                        \Schoolbox\Fixtures\Matchup\Mapper::getInstance(),
                        \Schoolbox\Fixtures\Team\Player\Mapper::getInstance()
                    ],
                    true,
                    $sentry
                ));

                /* SUDS Timetable
                 */
                $api->add(buildApiClass(
                    Calendar_Api_EventType_SudsTimetable_Schoolbox_Default::class,
                    [
                        $user,
                        $viewer,
                        null,
                        \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                        \Schoolbox\External\ClassEntryMapper::getInstance(),
                        \Schoolbox\External\ClassInvolvementMapper::getInstance(),
                        \Schoolbox\External\Event\Mapper::getInstance(),
                        \Schoolbox\External\Event\Overrides\Mapper::getInstance(),
                        \Schoolbox\UserMapper::getInstance(),
                        \Timetable_ColourMapper::getInstance()
                    ],
                    true,
                    $sentry
                ));

                /* SUDS Event
                 */
                $api->add(buildApiClass(
                    Calendar_Api_EventType_SudsEvent_Schoolbox_Default::class,
                    [
                        $user,
                        $viewer,
                        null,
                        \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                        \Schoolbox\External\Event\Mapper::getInstance(),
                        \Schoolbox\External\Event\Campus\Mapper::getInstance(),
                        \Schoolbox\External\Event\Roletype\Mapper::getInstance()
                    ],
                    true,
                    $sentry
                ));

                /* timetable days
                */
                $api->add(buildApiClass(
                    Calendar_Api_EventType_TimetableDays_External_Default::class,
                    [
                        $user,
                        $viewer,
                        \Schoolbox\Calendar\EventTypeMapper::getInstance()
                    ],
                    true,
                    $sentry
                ));

                //set the end of the export
                $endTimestamp = strtotime("+{$timetable_forward} months", $_SERVER['REQUEST_TIME']);
            }
            break;

        case '':
            throw new SchoolboxException('An export type must be provided', 400);
            break;

        default:
            throw new SchoolboxException('Invalid export type', 400);
            break;
    }
} catch (Throwable $e) {
    /* Figure out how to render errors based on the export format
     * CSV errors can render to screen
     */
    if ($exporter instanceof CSVExporter) {
        throw $e;
    }

    /* Everything else is probably accessed by another app
     */
    http_response_code($e->getCode());
    exit;
}

$renderer = new Renderer(
    $api,
    $startTimestamp,
    $endTimestamp,
    $user,
    $kernel->get('authorizer'),
    $kernel->get('router'),
    $viewer
);

$exporter->export($renderer, $filename);
