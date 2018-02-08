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

$kernel = require_once(dirname(__FILE__) . '/../../library/Bootstrap.php');

use Alaress\Database\Connector\IConnector;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Calendar\Event\Collection\Exporter;
use Calendar\Event\Collection\CSVExporter;
use EOTC\ConfigMapper as EOTCConfigMapper;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Permission\Access\Access;
use Schoolbox\UserMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Connector\Factory as ExternalConnectorFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Mapper\Exception;


/**
 * Returns the config id for the specified calendar connector.
 *
 * @param string $className
 * @return int
 * @throws UnexpectedValueException If connector is not configured or not configured as active
 */
function getConnectorId(string $className)
{
    $connectorConfig = Calendar_Api_Config_Mapper::getInstance()->findOneForClass($className);
    if (Calendar_Api_Config_Record::ACTIVE !== $connectorConfig->getStatus()) {
        throw new UnexpectedValueException("Calendar connector is not configured as active: $className");
    }
    return $connectorConfig->getId();
}

try {
    $token = $_GET['token'];
    $export_type = $_GET['export'];
    $api = null;
    $filename = 'calendar';
    $resourceId = $_GET['resource_id'];
    $resourceCategoryId = $_GET['resource_category_id'];
    $eventTypeId = $_GET['event_type'];
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

    $back = (int)ConfigMapper::getInstance()->get('calendar_export_start');
    $forward = (int)ConfigMapper::getInstance()->get('calendar_export_end');
    $timetable_forward = (int)ConfigMapper::getInstance()->get('timetable_export_end');

    $back = $back ? abs($back) : 1;
    $forward = $forward ? abs($forward) : 12;
    $timetable_forward = $timetable_forward ? abs($timetable_forward) : 3;

    // Default start and end times
    $start = strtotime("-{$back} months", $_SERVER['REQUEST_TIME']);
    $end = strtotime("+{$forward} months", $_SERVER['REQUEST_TIME']);

    switch ($export_type) {
        case 'personal':
            $api = new Calendar_Api_EventType_Event_Schoolbox_Personal(
                getConnectorId(Calendar_Api_EventType_Event_Schoolbox_Personal::class),
                $user
            );
            $filename = 'personal';
            break;

        case 'group':
            $export_id = intval($_GET['group']);
            if ($export_id <= 0) {
                throw new SchoolboxException('A group ID must be provided', 400);
            }
            $api = new Calendar_Api_EventType_Event_Schoolbox_Group(
                getConnectorId(Calendar_Api_EventType_Event_Schoolbox_Group::class),
                $user,
                $export_id
            );
            $filename = sprintf('group%d', $export_id);
            break;

        case 'campus':
            $export_id = intval($_GET['campus']);
            if ($export_id <= 0) {
                throw new SchoolboxException('A campus ID must be provided', 400);
            }
            $api = new Calendar_Api_EventType_Event_Schoolbox_Campus(
                getConnectorId(Calendar_Api_EventType_Event_Schoolbox_Campus::class),
                $user,
                $export_id
            );
            $filename = sprintf('campus%d', $export_id);
            break;

        case 'duework':

            $page = new SitePage(false);
            $page->setDi($kernel->getDependencyInjector());

            /** @var MapperProxy $assessmentDataSource */
            $assessmentDataSource  = $page->getDi()->get('assessmentDataSource');
            $visibility            = new Visibility(Access::getInstance());
            $api                   = new Calendar_Api_EventType_Submission_Schoolbox_Default(
                getConnectorId(Calendar_Api_EventType_Submission_Schoolbox_Default::class),
                $user,
                $assessmentDataSource,
                $visibility
            );
            $filename = 'duework';
            break;

        case 'timetable':
            $api = new Calendar_Api_EventType_Timetable_ExternalConstrained_Default(
                getConnectorId(Calendar_Api_EventType_Timetable_ExternalConstrained_Default::class),
                $user
            );
            $filename = 'timetable';
            $start = $_SERVER['REQUEST_TIME'];
            $end = strtotime("+{$timetable_forward} months", $_SERVER['REQUEST_TIME']);
            break;

        case 'timetabledays':
            $api = new Calendar_Api_EventType_TimetableDays_External_Default(
                getConnectorId(Calendar_Api_EventType_TimetableDays_External_Default::class),
                $user
            );
            $filename = 'timetabledays';
            $start = $_SERVER['REQUEST_TIME'];
            $end = strtotime("+{$timetable_forward} months", $_SERVER['REQUEST_TIME']);
            break;

        case 'resources':
            $api = new Calendar_Api_EventType_ResourceBooking_Booking_Default(
                getConnectorId(Calendar_Api_EventType_ResourceBooking_Booking_Default::class),
                $user
            );
            $filename = 'resources';
            break;

        case 'resource':
            $api = new Calendar_Api_EventType_ResourceBooking_Booking_ForItem(
                getConnectorId(Calendar_Api_EventType_ResourceBooking_Booking_ForItem::class),
                $user,
                $resourceId
            );
            $filename = sprintf('resource%d', $resourceId);
            break;

        case 'resource_category':
            $api = new Calendar_Api_EventType_ResourceBooking_Booking_ForCategory(
                getConnectorId(Calendar_Api_EventType_ResourceBooking_Booking_ForCategory::class),
                $user,
                $resourceCategoryId
            );
            $filename = sprintf('resourceCategory%d', $resourceCategoryId);
            break;

        case 'event_type':
            $api = new Calendar_Api_EventType_Event_Schoolbox_Type(
                getConnectorId(Calendar_Api_EventType_Event_Schoolbox_Type::class),
                $user,
                $eventTypeId
            );
            $filename = sprintf('eventType%d', $eventTypeId);
            break;

        case 'external':
            /** @var IConnector $databaseExternal */
            $databaseExternal = $kernel->getDependencyInjector()->get('databaseExternal');
            $factory = new ExternalConnectorFactory();
            $externalEvents = $factory->build(new \Schoolbox\Config\Loader(), $databaseExternal, ExternalConnectorFactory::EXTERNAL_EVENTS);
            $api = new Calendar_Api_EventType_Event_External_Default(
                getConnectorId(Calendar_Api_EventType_Event_External_Default::class),
                $user,
                $externalEvents
            );
            $filename = 'external';
            break;

        /* All EOTC Events
         */
        case 'eotc_all':

            if (EOTCConfigMapper::getInstance()->isSubscriptionEnabled()) {
                $api = new Calendar_Api_EventType_Custom_EOTC_Default(
                    getConnectorId(Calendar_Api_EventType_Custom_EOTC_Default::class),
                    $user
                );
                $filename = 'eotc_all';
            } else {

                /* Normally you wouldn't get here because the ui option is hidden when
                 * eotc isn't available - the only way you'd get here is direct url access
                 */
                throw new SchoolboxException('EOTC module not enabled', 400);
            }

            break;

        case 'all':

            // All day events, but exclude other sources
            $api = new Calendar_Api_Composite();
            $api->add(new Calendar_Api_EventType_Event_Schoolbox_Default(
                getConnectorId(Calendar_Api_EventType_Event_Schoolbox_Default::class),
                $user
            ));
            try {
                // Resource bookings too for now, as there's no other place for them
                $api->add(new Calendar_Api_EventType_ResourceBooking_Booking_Default(
                    getConnectorId(Calendar_Api_EventType_ResourceBooking_Booking_Default::class),
                    $user
                ));
                $api->add(new Calendar_Api_EventType_ResourceBooking_Moderation_Default(
                    getConnectorId(Calendar_Api_EventType_ResourceBooking_Moderation_Default::class),
                    $user
                ));

                if (EOTCConfigMapper::getInstance()->isSubscriptionEnabled()) {
                    $api->add(
                        new Calendar_Api_EventType_Custom_EOTC_Default(
                            getConnectorId(Calendar_Api_EventType_Custom_EOTC_Default::class),
                            $user
                        )
                    );
                }
            } catch (DomainException $e) {
                // Do nothing. This is expected if Resource Booking is disabled
            }
            break;

        case '':
            throw new SchoolboxException('An export type must be provided', 400);
            break;

        default:
            throw new SchoolboxException('Invalid export type', 400);
            break;
    }

    if ($_GET['format'] == 'csv') {
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
} catch (SchoolboxException|UnexpectedValueException|ServiceNotFound|RuntimeException $e) {
    $code = $e->getCode();
    switch ($code) {
        case 400:
        case 401:
            break;

        default:
            $code = 500;
            break;
    }
    http_response_code($code);
    echo $e->getMessage();
}
