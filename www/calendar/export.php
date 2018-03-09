<?php
/**
 * Updated calendar exporter
 *
 * Now supports more types of export than just all events at once, and supports
 * export for events for a user, given a token.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/** @var Kernel $kernel */
$kernel = require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');

use Alaress\Application\Kernel;
use Alaress\Database\Connector\IConnector;
use Calendar\Event\Collection\CSVExporter;
use Calendar\Event\Collection\Exporter;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Connector\Factory as ExternalConnectorFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\Access;
use Schoolbox\UserMapper;

/**
 * Returns the config id for the specified calendar connector.
 *
 * @param string $className
 * @return Calendar_Api_Config_Record
 * @throws UnexpectedValueException If connector is not configured or not configured as active
 */
function getConnector(string $className)
{
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
 * Builds an API class, and also returns an appropriate response code.
 *
 * @todo move this to a factory class; but there already is one, Calendar_Api_Factory
 *
 * @param string            $class   The class to build
 * @param array             $params  Params to pass into its constructor
 * @param bool              $enabled Is this class enabled? 404 if not
 * @param null|Raven_Client $sentry  If something goes wrong, report it here
 * @return array
 * @throws ReflectionException if $class doesn't exist
 */
function buildApiClass(string $class, array $params, bool $enabled, ?Raven_Client $sentry): array
{
    if (!$enabled) {
        // Not enabled; this is fine. Response code: 404
        return [new Calendar_Api_Composite(), 404];
    }

    try {
        $configuration = getConnector($class);
    } catch (UnexpectedValueException $e) {
        // Config missing: this is not fine, log to Sentry. Response code: 500
        if ($sentry) {
            $sentry->captureException($e);
        }
        return [new Calendar_Api_Composite(), 500];
    }

    if ($configuration->isActive()) {
        // Active: build API class. Response code: 200
        $constructorParams = array_merge([$configuration->getId()], $params);
        $reflectionClass   = new ReflectionClass($class);
        return [$reflectionClass->newInstanceArgs($constructorParams), 200];
    }

    // Inactive: this is fine. Response code: 404
    return [new Calendar_Api_Composite(), 404];
}


// Actually build the export
try {
    $token              = $_GET['token'];
    $export_type        = $_GET['export'];
    $code               = 200;
    $filename           = 'calendar';
    $resourceId         = $_GET['resource_id'];
    $resourceCategoryId = $_GET['resource_category_id'];
    $eventTypeId        = $_GET['event_type'];
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

    // Default start and end times
    $start = strtotime("-{$back} months", $_SERVER['REQUEST_TIME']);
    $end   = strtotime("+{$forward} months", $_SERVER['REQUEST_TIME']);

    // Report errors in this thing
    /** @var Raven_Client|null $sentry */
    $sentry = $kernel->getDependencyInjector()->has('sentry')
        ? $kernel->getDependencyInjector()->get('sentry')
        : null;

    switch ($export_type) {
        case 'personal':
            [$api, $code] = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Personal::class,
                [$user],
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

            [$api, $code] = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Group::class,
                [$user, $export_id],
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

            [$api, $code] = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Campus::class,
                [$user, $export_id],
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

            [$api, $code] = buildApiClass(
                Calendar_Api_EventType_Submission_Schoolbox_Default::class,
                [$user, $assessmentDataSource, $visibility],
                true,
                $sentry
            );

            $filename = 'duework';
            break;

        case 'timetable':
            [$api, $code] = buildApiClass(
                Calendar_Api_EventType_Timetable_ExternalConstrained_Default::class,
                [$user],
                true,
                $sentry
            );

            $filename = 'timetable';
            $start    = $_SERVER['REQUEST_TIME'];
            $end      = strtotime("+{$timetable_forward} months", $_SERVER['REQUEST_TIME']);
            break;

        case 'timetabledays':
            [$api, $code] = buildApiClass(
                Calendar_Api_EventType_TimetableDays_External_Default::class,
                [$user],
                true,
                $sentry
            );

            $filename = 'timetabledays';
            $start    = $_SERVER['REQUEST_TIME'];
            $end      = strtotime("+{$timetable_forward} months", $_SERVER['REQUEST_TIME']);
            break;

        case 'resources':
            /* Only include this api if Resource Booking is enabled by Feature Flag
             */
            [$api, $code] = buildApiClass(
                Calendar_Api_EventType_ResourceBooking_Booking_Default::class,
                [$user],
                (bool)ConfigMapper::getInstance()->get('resource_booking_enabled'),
                $sentry
            );

            $filename = 'resources';
            break;

        case 'resource':
            /* Only include this api if Resource Booking is enabled by Feature Flag
             */
            [$api, $code] = buildApiClass(
                Calendar_Api_EventType_ResourceBooking_Booking_ForItem::class,
                [$user, $resourceId],
                (bool)ConfigMapper::getInstance()->get('resource_booking_enabled'),
                $sentry
            );

            $filename = sprintf('resource%d', $resourceId);
            break;

        case 'resource_category':
            /* Only include this api if Resource Booking is enabled by Feature Flag
             */
            [$api, $code] = buildApiClass(
                Calendar_Api_EventType_ResourceBooking_Booking_ForCategory::class,
                [$user, $resourceCategoryId],
                (bool)ConfigMapper::getInstance()->get('resource_booking_enabled'),
                $sentry
            );

            $filename = sprintf('resourceCategory%d', $resourceCategoryId);
            break;

        case 'event_type':
            [$api, $code] = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Type::class,
                [$user, $eventTypeId],
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

            [$api, $code] = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Type::class,
                [$user, $externalEvents],
                true,
                $sentry
            );

            $filename = 'external';
            break;

        /* All EOTC Events
         */
        case 'eotc_all':
            [$api, $code] = buildApiClass(
                Calendar_Api_EventType_Custom_EOTC_Default::class,
                [$user],
                (bool)ConfigMapper::getInstance()->get('eotc_enabled'),
                $sentry
            );

            break;

        case 'all':
            // All day events, but exclude other sources
            $api = new Calendar_Api_Composite();
            [$subApi] = buildApiClass(
                Calendar_Api_EventType_Event_Schoolbox_Default::class,
                [$user],
                true,
                $sentry
            );
            $api->add($subApi);

            // Only include these api if Resource Booking is enabled by Feature Flag
            $resourceBookingEnabled = (bool)ConfigMapper::getInstance()->get('resource_booking_enabled');
            if ($resourceBookingEnabled) {
                [$subApi] = buildApiClass(
                    Calendar_Api_EventType_ResourceBooking_Booking_Default::class,
                    [$user],
                    true,
                    $sentry
                );
                $api->add($subApi);
                [$subApi] = buildApiClass(
                    Calendar_Api_EventType_ResourceBooking_Moderation_Default::class,
                    [$user],
                    true,
                    $sentry
                );
                $api->add($subApi);
            }

            // Only include EOTC if enabled
            $eotcEnabled = (bool)ConfigMapper::getInstance()->get('eotc_enabled');
            if ($eotcEnabled) {
                [$subApi] = buildApiClass(
                    Calendar_Api_EventType_Custom_EOTC_Default::class,
                    [$user],
                    true,
                    $sentry
                );
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
    $code = $e->getCode();
    switch ($code) {
        case 400:
        case 401:
            break;

        default:
            $code = 500;
            break;
    }

    // Send exception to sentry, and output empty API
    if ($sentry) {
        $sentry->captureException($e);
    }
    $api = new Calendar_Api_Composite();
}

// Always output here
http_response_code($code);
if ($_GET['format'] === 'csv') {
    header('Cache-Control: private');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: filename=' . $filename . '.csv');

    $exporter = new CSVExporter(
        $api,
        $start,
        $end,
        $user,
        new SitePage(false),
        $viewer
    );
} else {
    header('Cache-Control: private');
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: filename=' . $filename . '.ics');

    $exporter = new Exporter(
        $api,
        $start,
        $end,
        $user,
        new SitePage(false),
        $viewer
    );
}

$exporter->renderAll();
