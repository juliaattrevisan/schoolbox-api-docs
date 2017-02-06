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

use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Calendar\Event\Collection\Exporter;
use Calendar\Event\Collection\CSVExporter;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Config\ConfigMapper;

try {
    $token = $_GET['token'];
    $export_type = $_GET['export'];
    $api = null;
    $filename = 'calendar';
    $resourceId = $_GET['resource_id'];
    $resourceCategoryId = $_GET['resource_category_id'];
    $eventTypeId = $_GET['event_type'];
    if($resourceId > 0) {
        $export_type = 'resource';
    }
    if($resourceCategoryId >0) {
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
    $end   = strtotime("+{$forward} months", $_SERVER['REQUEST_TIME']);

    switch ($export_type) {
        case 'personal':
            $api = new Calendar_Api_EventType_Event_Schoolbox_Personal($user, array());
            $filename = 'personal';
            break;

        case 'group':
            $api = new Calendar_Api_EventType_Event_Schoolbox_Group($user, array());
            $export_id = intval($_GET['group']);
            if ($export_id > 0) {
                $api->setGroupId($export_id);
            } else {
                throw new SchoolboxException('A group ID must be provided', 400);
            }
            $filename = sprintf('group%d', $export_id);
            break;

        case 'campus':
            $api = new Calendar_Api_EventType_Event_Schoolbox_Campus($user, array());
            $export_id = intval($_GET['campus']);
            if ($export_id > 0) {
                $api->setCampusId($export_id);
            } else {
                throw new SchoolboxException('A campus ID must be provided', 400);
            }
            $filename = sprintf('campus%d', $export_id);
            break;

        case 'duework':

            $page = new SitePage(false);
            $page->setDi($kernel->getDependencyInjector());
            $api = new Calendar_Api_EventType_Submission_Schoolbox_Default($user, array(),  $page->getDi()->get('assessmentDataSource'));
            $filename = 'duework';
            break;

        case 'timetable':
            $api = new Calendar_Api_EventType_Timetable_External_Default($user, array());
            $filename = 'timetable';
            $start = $_SERVER['REQUEST_TIME'];
            $end = strtotime("+{$timetable_forward} months", $_SERVER['REQUEST_TIME']);
            break;

        case 'timetabledays':
            $api = new Calendar_Api_EventType_TimetableDays_External_Default($user, array());
            $filename = 'timetabledays';
            $start = $_SERVER['REQUEST_TIME'];
            $end = strtotime("+{$timetable_forward} months", $_SERVER['REQUEST_TIME']);
            break;

        case 'resources':
            $api = new Calendar_Api_EventType_ResourceBooking_Booking_Default($user, array());
            $filename = 'resources';
            break;

        case 'resource':
            $api = new Calendar_Api_EventType_ResourceBooking_Booking_ForItem($user, array());
            $api->setResourceId($resourceId);
            $filename =  sprintf('resource%d', $resourceId);
            break;

        case 'resource_category':
            $api = new Calendar_Api_EventType_ResourceBooking_Booking_ForCategory($user, array());
            $api->setCategoryId($resourceCategoryId);
            $filename =  sprintf('resourceCategory%d', $resourceCategoryId);
            break;

        case 'event_type':
            $api = new Calendar_Api_EventType_Event_Schoolbox_Type($user, array());
            $api->setEventTypeId($eventTypeId);
            $filename =  sprintf('eventType%d', $eventTypeId);
            break;

        case 'all':

            // All day events, but exclude other sources
            $api = new Calendar_Api_Composite();
            $api->add(new Calendar_Api_EventType_Event_Schoolbox_Default($user, array()));
            try {
                // Resource bookings too for now, as there's no other place for them
                $api->add(new Calendar_Api_EventType_ResourceBooking_Booking_Default($user, array()));
                $api->add(new Calendar_Api_EventType_ResourceBooking_Moderation_Default($user, array()));
            } catch(DomainException $e){
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


    if($_GET['format'] == 'csv') {

        header('Cache-Control: private');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: filename='.$filename.'.csv');

        $exporter = new CSVExporter(
            $api,
            $start,
            $end,
            $user,
            new SitePage(false),
            $viewer
        );
    }
    else {
        header('Cache-Control: private');
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: filename='.$filename.'.ics');

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
} catch (SchoolboxException $e) {
    //
    // I wish I could use http_response_code() method here!
    $code = $e->getCode();
    $text = '';
    switch ($code) {
        case 400:
            $text = 'Bad Request';
            break;

        case 401:
            $text = 'Unauthorized';
            break;

        default:
            $code = 500;
            $text = 'Internal Server Error';
            break;
    }
    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');
    header($protocol . ' ' . $code . ' ' . $text);
    echo $e->getMessage();
}
