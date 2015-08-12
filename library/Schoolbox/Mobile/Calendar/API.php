<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Mobile\Calendar;

use Authenticator;
use Calendar_Api_Config_Mapper;
use Calendar_Api_Config_Record;
use Calendar_Api_Factory;
use Calendar_Event_Type_Abstract;
use ClientDevice;
use Exception;
use Response_HTTP;
use Schoolbox\Mobile\Generic;
use Schoolbox\UserMapper;
use Scrub;

class API extends Generic\API
{

    public function Month($date = '', $user = null)
    {
        try {
            $user = $this->getUserForCalendar($user);
        } catch (\Exception_Schoolbox_NotAuthenticated $e) {
            return new Response_HTTP(Response_HTTP::HTTP_UNAUTHORIZED);
        } catch (\Exception_Schoolbox_AccessDenied $e) {
            return new Response_HTTP(Response_HTTP::HTTP_FORBIDDEN);
        } catch (\Exception_Schoolbox_NotFound $e) {
            return new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return new Response_HTTP(Response_HTTP::HTTP_SERVER_ERROR);
        }

        $response = new Response_HTTP();
        $selected = $this->parseDate($date);

        // Get events with a 1 day buffer at end of month, to ensure that last
        // day's events get gotten.
        $month['start'] = mktime(0, 0, 0, $selected['month'], 1, $selected['year']);
        $month['first'] = date('w', $month['start']);
        $month['end']   = strtotime("+1 month +1 day", $month['start']);

        $nextMonth = mktime(0, 0, 0, $selected['month'] + 1, 1, $selected['year']);
        $prevMonth = mktime(0, 0, 0, $selected['month'] - 1, 1, $selected['year']);

        $nextMonthSlug = date('d-m-Y', $nextMonth) .
            ($_SESSION['user']->getId() == $user->getId() ? '' : '/' . $user->getId());
        $prevMonthSlug = date('d-m-Y', $prevMonth) .
            ($_SESSION['user']->getId() == $user->getId() ? '' : '/' . $user->getId());

        /* $eventMap is an array of empty event containers
         * Each array element maps to a day of the month
         */
        $eventMap   = array();
        $currentDay = strtotime("-1 day", $month['start']);

        /* Insert blank days to offset day of week of first day of month
         */
        $blankStart = array();

        for ($i = 0; $i < $month['first']; $i++) {
            $blankStart[] = '_blank';
        }

        $groupedEventList = $this->getEventsForTimespan($month['start'], $month['end'], $user);

        /* Prefill containers for each day of the month
         */
        for ($i = $month['start']; $i < $month['end']; $i += 24 * 3600) {
            $currentDay = strtotime("+1 day", $currentDay);
            $eventGroup = date('Ymd', $currentDay);
            // Filter by current month/year
            if (date('m', $currentDay) != $selected['month']
                || date('Y', $currentDay) != $selected['year']
            ) {
                continue;
            }
            $day = array(
                'dayOfMonth' => date('j', $currentDay),
                'niceDay'    => date('jS', $currentDay),
                'events'     => array(),
            );
            if (date('d-m-Y', $currentDay) == date('d-m-Y', $_SERVER['REQUEST_TIME'])) {
                $day['isToday'] = '1';
            }
            $day['events']                = $groupedEventList[$eventGroup] ? : array();
            $eventMap[$day['dayOfMonth']] = $day;
        }

        /* Pad out the final line
         */
        $blankEnd = array();

        /* Find the next multiple of seven, to create the correct number of blankies
         * at the end of the calendar map list
         */
        $numBlanks = 7 - ((count($blankStart) + count($eventMap)) % 7);

        /* Don't pad out a whole new empty week if the blank start + month ends cleanly
         */
        if ($numBlanks < 7) {
            for ($i = 0; $i < $numBlanks; $i++) {
                $blankEnd[] = '_blank';
            }
        }

        /* Manufacture $calData into the original structure
         */
        $calData = array_merge($blankStart, $eventMap, $blankEnd);

        $aside = sprintf(
            '<a href="%s" data-icon="back">%s</a><a href="%s" data-icon="next">%s</a>',
            "/m/calendar/month/$prevMonthSlug",
            '',
            "/m/calendar/month/$nextMonthSlug",
            ''
        );

        $calendar = array(
            'title'   => date('M Y', $month['start']),
            'aside'   => $aside,
            'class'   => 'wide',
            'icon'    => '',
            'content' => $calData
        );

        $response->setPage('title', 'Month Calendar');
        $response->setResponse('articles', array('Month' => $calendar));

        return $response;

    }

    public function Week($date = '', $user = null)
    {
        try {
            $user = $this->getUserForCalendar($user);
        } catch (\Exception_Schoolbox_NotAuthenticated $e) {
            return new Response_HTTP(Response_HTTP::HTTP_UNAUTHORIZED);
        } catch (\Exception_Schoolbox_AccessDenied $e) {
            return new Response_HTTP(Response_HTTP::HTTP_FORBIDDEN);
        } catch (\Exception_Schoolbox_NotFound $e) {
            return new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return new Response_HTTP(Response_HTTP::HTTP_SERVER_ERROR);
        }

        $response = new Response_HTTP();
        $selected = $this->parseDate($date);

        $dayOfWeek   = date("w", mktime(0, 0, 0, $selected['month'], $selected['day'], $selected['year']));
        $startOfWeek = mktime(0, 0, 0, $selected['month'], ($selected['day'] - $dayOfWeek), $selected['year']);
        $endOfWeek   = strtotime("+6 days", $startOfWeek);

        if (date("m", $startOfWeek) != date("m", $endOfWeek)) {
            $title = date('jS F', $startOfWeek) . ' - ' . date("jS F", $endOfWeek);
        } else {
            $title = date("jS", $startOfWeek) . ' - ' . date('jS F', $endOfWeek);
        }

        $calendar = array();

        $groupedEventList = $this->getEventsForTimespan($startOfWeek, $endOfWeek - 1, $user);

        for ($i = 0; $i < 7; $i++) {
            $currentDay = strtotime("+$i day", $startOfWeek);
            $eventDay   = date('Ymd', $currentDay);

            $calendar[] = array(
                'title'   => date('jS F', $currentDay),
                'aside'   => '',
                'class'   => (date('Ymd') == $eventDay ? 'cal-today ' : ' ') . 'wide',
                'icon'    => '',
                'content' => $groupedEventList[$eventDay] ? : array()
            );
        }

        $response->setPage('title', 'Week Calendar');
        $response->setResponse('articles', $calendar);

        return $response;
    }

    public function Agenda($date = '', $limit = 7, $user = null)
    {
        try {
            $user = $this->getUserForCalendar($user);
        } catch (\Exception_Schoolbox_NotAuthenticated $e) {
            return new Response_HTTP(Response_HTTP::HTTP_UNAUTHORIZED);
        } catch (\Exception_Schoolbox_AccessDenied $e) {
            return new Response_HTTP(Response_HTTP::HTTP_FORBIDDEN);
        } catch (\Exception_Schoolbox_NotFound $e) {
            return new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return new Response_HTTP(Response_HTTP::HTTP_SERVER_ERROR);
        }

        $response = new Response_HTTP();
        $selected = $this->parseDate($date);

        $dayOfWeek   = date("w", mktime(0, 0, 0, $selected['month'], $selected['day'], $selected['year']));
        $startOfWeek = mktime(0, 0, 0, $selected['month'], ($selected['day'] - $dayOfWeek), $selected['year']);

        $events = array();

        $groupedEventList = $this->getEventsForTimespan(
            $startOfWeek,
            strtotime("+$limit day - 1 second", $startOfWeek),
            $user
        );

        for ($i = 0; $i < $limit; $i++) {
            $currentDay = strtotime("+$i day", $startOfWeek);
            $dayGroup   = date('Ymd', $currentDay);
            $dayEvents  = $groupedEventList[$dayGroup] ? : array();
            if (is_array($dayEvents)) {
                foreach ($dayEvents as $j => $event) {
                    $events[] = array(
                        'header' => ($j == 0 ? date('jS F', $currentDay) : ''),
                        'event'  => $event,
                    );
                }
            }
        }

        $calendar = array(
            'title'   => 'Agenda',
            'aside'   =>
                '<a data-icon="next" href="/m/calendar/agenda/' .
                date('d-m-Y') . '/' . ($limit + 2) . '">more</a>',
            'class'   => '',
            'icon'    => '',
            'content' => $events
        );

        $response->setPage('title', 'Agenda');
        $response->setResponse('articles', array($calendar));

        return $response;
    }

    protected function parseDate($date)
    {
        try {
            list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $date);
        } catch (Exception $e) {
            list($selectedDay, $selectedMonth, $selectedYear) = explode('-', date('d-m-Y'));
        }

        $selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);
        if ($selectedDate <= 0) {
            $selectedDate = time();
        }

        return array(
            'day'   => date("j", $selectedDate),
            'month' => date("n", $selectedDate),
            'year'  => date("Y", $selectedDate)
        );
    }

    /**
     * Return a listing of dates with events in them.
     * Depending on $_SESSION['env'], will return a week or a month
     *
     * @param string $date
     *
     * @return Response_HTTP
     */
    public function Display($date = '')
    {
        $auth = new Authenticator();
        $user = $auth->getSessionUser();

        try {
            list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $date);
        } catch (Exception $e) {
            list($selectedDay, $selectedMonth, $selectedYear) = explode('-', date('d-m-Y'));
        }

        $selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);
        if ($selectedDate <= 0) {
            $selectedDate = time();
        }

        $selected['day']   = date("j", $selectedDate);
        $selected['month'] = date("n", $selectedDate);
        $selected['year']  = date("Y", $selectedDate);

        // display month view on tablets, otherwise fall back to the weekly list
        if (isset($_SESSION['env']) && $_SESSION['env']['type'] == ClientDevice::TYPE_TABLET) {
            $response = $this->displayMonth($selected, $user);
        } else {
            $response = $this->displayWeek($selected, $user);
        }

        return $response;
    }


    private function displayWeek($selected, $user)
    {
        $response = new Response_HTTP();

        $dayOfWeek   = date("w", mktime(0, 0, 0, $selected['month'], $selected['day'], $selected['year']));
        $startOfWeek = mktime(0, 0, 0, $selected['month'], ($selected['day'] - $dayOfWeek), $selected['year']);
        $endOfWeek   = strtotime("+6 days", $startOfWeek);

        if (date("m", $startOfWeek) != date("m", $endOfWeek)) {
            $title = date('jS F', $startOfWeek) . ' - ' . date("jS F", $endOfWeek);
        } else {
            $title = date("jS", $startOfWeek) . ' - ' . date('jS F', $endOfWeek);
        }

        $calendar         = array();
        $groupedEventList = $this->getEventsForTimespan($startOfWeek, $endOfWeek - 1, $user);

        for ($i = 0; $i < 7; $i++) {
            $currentDay = strtotime("+$i day", $startOfWeek);
            $eventDay   = date('Ymd', $currentDay);

            $calendar[] = array(
                'title'   => date('jS F', $currentDay),
                'aside'   => '',
                'class'   => date('Ymd') == date('Ymd', $currentDay) ? 'cal-today' : '',
                'icon'    => '',
                'content' => $groupedEventList[$eventDay] ? : array()
            );
        }

        $response->setPage('title', 'Week Calendar');
        $response->setResponse('articles', $calendar);

        return $response;
    }


    private function displayMonth($selected, $user)
    {
        $response = new Response_HTTP();

        $week['day']   = date("w", mktime(0, 0, 0, $selected['month'], $selected['day'], $selected['year']));
        $week['start'] = mktime(0, 0, 0, $selected['month'], ($selected['day'] - $week['day']), $selected['year']);
        $week['end']   = strtotime("+6 days", $week['start']);

        $month['start'] = mktime(0, 0, 0, $selected['month'], 1, $selected['year']);
        $month['first'] = date('w', $month['start']);
        $month['end']   = strtotime("+1 month +1 day", $month['start']);

        $nextMonth = mktime(0, 0, 0, $selected['month'] + 1, 1, $selected['year']);
        $prevMonth = mktime(0, 0, 0, $selected['month'] - 1, 1, $selected['year']);

        $nextMonthSlug = date('d-m-Y', $nextMonth);
        $prevMonthSlug = date('d-m-Y', $prevMonth);

        $nextMonthName = date('F', $nextMonth);
        $prevMonthName = date('F', $prevMonth);

        $calData = array();

        $currentDay = strtotime("-1 day", $month['start']);

        // insert blank days to offset day of week of first day of month
        for ($i = 0; $i < $month['first']; $i++) {
            $calData[] = '_blank';
        }

        $groupedEventList = $this->getEventsForTimespan($month['start'], $month['end'], $user);
        for ($i = $month['start']; $i < $month['end']; $i += 24 * 3600) {
            $currentDay = strtotime("+1 day", $currentDay);

            $calData[] = array(
                'day'    => date('jS', $currentDay),
                'events' => $groupedEventList[date('Ymd', $currentDay)] ? : array($currentDay)
            );
        }

        // pad out the final line
        for ($i = 0; $i < count($calData) % 7; $i++) {
            $calData[] = '_blank';
        }

        $aside = sprintf(
            '<a href="%s" data-icon="back">%s</a><a href="%s" data-icon="next">%s</a>',
            "/m/calendar/display/$prevMonthSlug",
            $prevMonthName,
            "/m/calendar/display/$nextMonthSlug",
            $nextMonthName
        );

        $calendar = array(
            'title'   => date('F', $currentDay),
            'aside'   => $aside,
            'class'   => 'wide',
            'icon'    => '',
            'content' => $calData
        );

        $response->setPage('title', 'Month Calendar');
        $response->setResponse('articles', array('Month' => $calendar));

        return $response;
    }

    /**
     * Retrieves and groups all events by day for the given timespan.
     *
     * @param  int    $start       Timestamp of start of search bracket
     * @param  int    $end         Timestamp of end of search bracket
     * @param  User   $user        The ID of the user being retrieved
     * @param  string $groupFormat Format to group retrieved event list by
     *
     * @return array
     */
    protected function getEventsForTimespan($start, $end, $user = null, $groupFormat = 'Ymd')
    {
        // grab the session user if one wasn't supplied
        $user            = $user ? : $_SESSION['user'];
        $calFactory      = new Calendar_Api_Factory($user);
        $calendarHandler = $calFactory->produce();
        /** @var Calendar_Event_Type_Abstract[]|\Collection $eventList */
        $eventList = $calendarHandler->getEvents($start, $end);
        $eventList->sort();

        $groupedEventList = array();
        foreach ($eventList as $event) {
            $eventGrouping = $event->getStartTime()->getUsingFormat($groupFormat);
            if (!isset($groupedEventList[$eventGrouping])) {
                $groupedEventList[$eventGrouping] = array();
            }
            $groupedEventList[$eventGrouping][] = $event;
        }
        return $groupedEventList;
    }


    /**
     * Return the data for a single event.
     *
     * @param $event_id
     * @param $connector_id
     *
     * @return Response_HTTP
     */
    public function Event($event_id, $connector_id)
    {
        try {
            /* Find out from whence this event came
                   * If the config was found, continue as normal
                   */
            /** @var Calendar_Api_Config_Record $configRecord */
            $configRecord = Calendar_Api_Config_Mapper::getInstance()->findById($connector_id);
            $config       = $configRecord->decodeConfig();
            $config->setId($connector_id);
            $factory = new Calendar_Api_Factory($_SESSION['user']);
            $factory->resetConfigs();
            $factory->addConfig($config);
            $composite = $factory->produce();
            /* Forgive me, for I have sinned
                   */
            $event = $composite->getEvent($event_id);
            /* Standardise into some format that mobile expects
                   */
            if ($event instanceof Calendar_Event_Type_Abstract) {
                $standardisedEvent = array(
                    'id'           => $event->getEventId(),
                    'series_id'    => '',
                    'owner'        => $event->getAuthorId(),
                    'access'       => $event->getAccess(),
                    'type'         => $event->getType(),
                    'title'        => $event->getTitle(),
                    'date'         => $event->getStartTime()->getDatabaseDate(),
                    'location'     => $event->getLocation(),
                    'attendance'   => method_exists($event, 'getAtendance') ? $event->getAttendance() : '',
                    'detail'       => Scrub::html($event->getDetail()),
                    'publish_date' => $event->getPublishDate(),
                    'allDay'       => $event->getAllday(),
                    'startTime'    => $event->getStartTime()->getTimeOnly(),
                    'endTime'      => $event->getEndTime()->getTimeOnly(),
                    /* The author's title - mr, mrs, etc. */
                    //'title' => '',
                    'firstname'    => $event->getAuthorName(),
                    'lastname'     => '',
                );
                $time              = array(
                    'title'   => 'Summary',
                    'aside'   => '',
                    'class'   => '',
                    'icon'    => 'clock',
                    'content' => $standardisedEvent
                );
                $detail            = array(
                    'title'   => 'Details',
                    'aside'   => '',
                    'class'   => '',
                    'icon'    => 'news',
                    'content' => array(
                        'detail' => $standardisedEvent['detail']
                    )
                );
                $response          = new Response_HTTP();
                $response->setPage('title', $standardisedEvent['title'] ? : 'Event');
                $response->setResponse('articles', array($time, $detail));
            } else {
                $response = new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            $response = new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
        }

        return $response;
    }

    /**
     * Retrieves the user to return a calendar for.
     *
     * @param mixed $userId
     *
     * @return \Schoolbox\User
     * @throws \Exception_Schoolbox_NotAuthenticated if user isn't logged in
     * @throws \Exception_Schoolbox_AccessDenied     if authd user can't see $userId's calendar
     * @throws \Exception_Schoolbox_NotFound         if $userId wasn't found
     */
    protected function getUserForCalendar($userId)
    {
        $currentUser = $_SESSION['user'];
        if (!($currentUser instanceof \Schoolbox\User)) {
            throw new \Exception_Schoolbox_NotAuthenticated();
        }
        try {
            $user = $userId ? UserMapper::getInstance()->findById($userId) : $currentUser;
            if ($user->getId() != $currentUser->getId()) {
                // visibility check
                $gHandler = new \Group();
                if ($gHandler->isStudent()) {
                    throw new \Exception_Schoolbox_AccessDenied();
                }
                if ($gHandler->isParent() && !$gHandler->isParentOfChild($user->getId())) {
                    throw new \Exception_Schoolbox_AccessDenied();
                }
            }
        } catch (\Schoolbox\Mapper\Exception $e) {
            throw new \Exception_Schoolbox_NotFound();
        }
        return $user;
    }
}
