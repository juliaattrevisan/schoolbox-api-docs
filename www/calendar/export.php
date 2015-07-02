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
require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

use Calendar\Event\Collection\Exporter;
use Calendar\Event\Collection\CSVExporter;
use Schoolbox\User;
use Schoolbox\UserMapper;



try {
    $token = $_GET['token'];
    $export_type = $_GET['export'];
    $api = null;
    $filename = 'calendar';
    if (empty($token)) {
        throw new Exception_Schoolbox('No token provided', 401);
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
        throw new Exception_Schoolbox('Invalid token', 401);
    }

    // Default start and end times
    $start = strtotime('-1 months', $_SERVER['REQUEST_TIME']);
    $end   = strtotime('+12 months', $_SERVER['REQUEST_TIME']);

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
                throw new Exception_Schoolbox('A group ID must be provided', 400);
            }
            $filename = sprintf('group%d', $export_id);
            break;

        case 'campus':
            $api = new Calendar_Api_EventType_Event_Schoolbox_Campus($user, array());
            $export_id = intval($_GET['campus']);
            if ($export_id > 0) {
                $api->setCampusId($export_id);
            } else {
                throw new Exception_Schoolbox('A campus ID must be provided', 400);
            }
            $filename = sprintf('campus%d', $export_id);
            break;

        case 'duework':
            $api = new Calendar_Api_EventType_Submission_Schoolbox_Default($user, array());
            $filename = 'duework';
            break;

        case 'timetable':
            $api = new Calendar_Api_EventType_Timetable_External_Default($user, array());
            $filename = 'timetable';
            $start = $_SERVER['REQUEST_TIME'];
            $end = strtotime('+3 months', $_SERVER['REQUEST_TIME']);
            break;

        case 'timetabledays':
            $api = new Calendar_Api_EventType_TimetableDays_External_Default($user, array());
            $filename = 'timetabledays';
            $start = $_SERVER['REQUEST_TIME'];
            $end = strtotime('+3 months', $_SERVER['REQUEST_TIME']);
            break;

        case 'all':

            // All day events, but exclude other sources
            $api = new Calendar_Api_Composite();
            $api->add(new Calendar_Api_EventType_Event_Schoolbox_Default($user, array()));
            // Resource bookings too for now, as there's no other place for them
            if (class_exists('Calendar_Api_EventType_ResourceBooking_Booking_Default')) {
                $api->add(new Calendar_Api_EventType_ResourceBooking_Booking_Default($user, array()));
            }
            if (class_exists('Calendar_Api_EventType_ResourceBooking_Moderation_Default')) {
                $api->add(new Calendar_Api_EventType_ResourceBooking_Moderation_Default($user, array()));
            }
            break;

        case '':
            throw new Exception_Schoolbox('An export type must be provided', 400);
            break;

        default:
            throw new Exception_Schoolbox('Invalid export type', 400);
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
            new SitePage(false)
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
            new SitePage(false)
        );
    }

    $exporter->renderAll();
} catch (Exception_Schoolbox $e) {
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
