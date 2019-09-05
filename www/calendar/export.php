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
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Connector\Factory as ExternalConnectorFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\ResourceBooking\Item\Mapper as ItemMapper;
use Schoolbox\ResourceBooking\Report\CalendarApiFactory;
use Schoolbox\UserMapper;
use Crell\ApiProblem\ApiProblem;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

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
 * Builds an API class.
 *
 * @todo move this to a factory class; but there already is one, Calendar_Api_Factory
 *
 * @param string            $class   The class to build
 * @param array             $params  Params to pass into its constructor
 * @param bool              $enabled Is this class enabled? 404 if not
 * @param null|Raven_Client $sentry  If something goes wrong, report it here
 * @return Calendar_Api_EventType_Interface
 * @throws ReflectionException if $class doesn't exist
 */
function buildApiClass(string $class, array $params, bool $enabled, ?Raven_Client $sentry): Calendar_Api_EventType_Interface
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


// Actually build the export
try {
    $token              = $_GET['token'];
    $export_type        = $_GET['export'];
    $filename           = 'calendar';
    $resourceId         = (int)$_GET['resource_id'];
    $resourceCategoryId = (int)$_GET['resource_category_id'];
    $eventTypeId        = (int)$_GET['event_type'];
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

    $back              = (int)ConfigMapper::getInstance()->get('calendar_export_start');
    $forward           = (int)ConfigMapper::getInstance()->get('calendar_export_end');
    $timetable_forward = (int)ConfigMapper::getInstance()->get('timetable_export_end');

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
    $startTimestamp = $startTimestamp ?: \strtotime("-{$back} months", $_SERVER['REQUEST_TIME']);
    $endTimestamp   = $endTimestamp ?: \strtotime("+{$forward} months", $_SERVER['REQUEST_TIME']);

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
    /** @var Raven_Client|null $sentry */
    $sentry = $kernel->getDependencyInjector()->has('sentry')
        ? $kernel->getDependencyInjector()->get('sentry')
        : null;

    switch ($export_type) {
        case 'personal':
            $api = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Personal::class,
                [$user, $viewer],
                true,
                $sentry
            );

            $filename = 'personal';
            break;

        case 'group':
            $export_id = (int)$_GET['group'];
            if ($export_id <= 0) {
                throw new SchoolboxException('A group ID must be provided', 400);
            }

            $api = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Group::class,
                [$user, $viewer, $export_id],
                true,
                $sentry
            );

            $filename = sprintf('group%d', $export_id);
            break;

        case 'campus':
            $export_id = (int)$_GET['campus'];
            if ($export_id <= 0) {
                throw new SchoolboxException('A campus ID must be provided', 400);
            }

            $api = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Campus::class,
                [$user, $viewer, $export_id],
                true,
                $sentry
            );

            $filename = sprintf('campus%d', $export_id);
            break;

        case 'duework':
            $page = new SitePage(false);
            $page->setDi($kernel->getDependencyInjector());

            /** @var MapperProxy $assessmentDataSource */
            $assessmentDataSource = $page->getDi()->get('assessmentDataSource');
            $visibility           = new Visibility(Access::getInstance());

            $api = buildApiClass(
                Calendar_Api_EventType_Submission_Schoolbox_Default::class,
                [$user, $viewer, $assessmentDataSource, $visibility],
                true,
                $sentry
            );

            $filename = 'duework';
            break;

        case 'timetable':
            $api = buildApiClass(
                Calendar_Api_EventType_Timetable_ExternalConstrained_Default::class,
                [$user, $viewer, $kernel->getDependencyInjector()->get('config')],
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
                [$user, $viewer],
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
                [$user, $viewer],
                (bool)ConfigMapper::getInstance()->get('resource_booking_enabled'),
                $sentry
            );

            $filename = 'resources';
            break;

        case 'resource':

            $api = new Calendar_Api_Composite();

            /* Only include this api if Resource Booking is enabled by Feature Flag
             */
            if (ConfigMapper::getInstance()->get('resource_booking_enabled')) {

                $item = ItemMapper::getInstance()->findById($resourceId);
                $roomCode = trim($item->getRoomCode());

                // Add the resource API
                $resourceApi = new Calendar_Api_EventType_ResourceBooking_Booking_ForItem(
                    Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                    $user,
                    $user,
                    [$resourceId]
                );

                $api->add($resourceApi);

                // If room code is set, get timetable for the room
                if (!empty($roomCode)) {
                    $timetableApi = new Calendar_Api_EventType_Timetable_Room(
                        Calendar_Api_Config_Mapper::CONFIGURATION_NONE,
                        $user,
                        $user,
                        $kernel->getDependencyInjector()->get('externalTimetable'),
                        [$roomCode => $item->getId()]
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
            if (ConfigMapper::getInstance()->get('resource_booking_enabled')) {

                $apiFactory = new CalendarApiFactory($user, $kernel->getDependencyInjector()->get('externalTimetable'));
                $api = $apiFactory->buildFromResourceCollection(
                    ItemMapper::getInstance()->findByColumn(
                        'resource_category_id',
                        $resourceCategoryId,
                        ['order' => ['name' => 'asc',]]
                    )
                );

                $endTimestamp = strtotime("+{$timetable_forward} months", $_SERVER['REQUEST_TIME']);

            } else {
                $api = new Calendar_Api_Composite();
            }

            $filename = sprintf('resourceCategory%d', $resourceCategoryId);
            break;

        case 'event_type':
            $api = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Type::class,
                [$user, $viewer, $eventTypeId],
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
                new \Schoolbox\Config\Loader(),
                $databaseExternal,
                ExternalConnectorFactory::EXTERNAL_EVENTS
            );

            $api = buildApiClass(
                Calendar_Api_EventType_Event_External_Default::class,
                [$user, $viewer, $externalEvents],
                true,
                $sentry
            );

            $filename = 'external';
            break;

        /* All EOTC Events
         */
        case 'eotc_all':
            $api = buildApiClass(
                Calendar_Api_EventType_Custom_EOTC_Default::class,
                [$user, $viewer],
                (bool)ConfigMapper::getInstance()->get('eotc_enabled'),
                $sentry
            );

            break;

        case 'all':
            // All day events, but exclude other sources
            $api = new Calendar_Api_Composite();
            $subApi = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Default::class,
                [$user, $viewer],
                true,
                $sentry
            );
            $api->add($subApi);

            // Only include these api if Resource Booking is enabled by Feature Flag
            $resourceBookingEnabled = (bool)ConfigMapper::getInstance()->get('resource_booking_enabled');
            if ($resourceBookingEnabled) {
                $subApi = buildApiClass(
                    Calendar_Api_EventType_ResourceBooking_Booking_Default::class,
                    [$user, $viewer],
                    true,
                    $sentry
                );
                $api->add($subApi);
                $subApi = buildApiClass(
                    Calendar_Api_EventType_ResourceBooking_Moderation_Default::class,
                    [$user, $viewer],
                    true,
                    $sentry
                );
                $api->add($subApi);
            }

            // Only include EOTC if enabled
            $eotcEnabled = (bool)ConfigMapper::getInstance()->get('eotc_enabled');
            if ($eotcEnabled) {
                $subApi = buildApiClass(
                    Calendar_Api_EventType_Custom_EOTC_Default::class,
                    [$user, $viewer],
                    true,
                    $sentry
                );
                $api->add($subApi);
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
    http_response_code($e->getCode());
    exit;
}

// Always output here
switch($_GET['format'] ?? ''){
    case 'csv':
        $exporter = new CSVExporter();
        break;
    case 'json':
        $exporter = new JsonExporter();
        break;
    default:
        $exporter = new VCalExporter();
}

$renderer = new Renderer(
    $api,
    $startTimestamp,
    $endTimestamp,
    $user,
    $kernel->getDependencyInjector(),
    $viewer
);

$exporter->export($renderer, $filename);
