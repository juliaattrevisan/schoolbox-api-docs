<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Calendar_API extends Generic_API {

    public function Month($date = '') {
        $response = new Response_HTTP();
        $selected = $this->parseDate($date);
        $calFactory = new Calendar_Api_Factory();
        $calendarHandler = $calFactory->produce();

        $month['start'] = mktime(0, 0, 0, $selected['month'], 1, $selected['year']);
        $month['first'] = date('w', $month['start']);
        $month['end']   = strtotime("+1 month -1 second", $month['start']);

        $nextMonth = mktime(0, 0, 0, $selected['month'] + 1, 1, $selected['year']);
        $prevMonth = mktime(0, 0, 0, $selected['month'] - 1, 1, $selected['year']);

        $nextMonthSlug = date('d-m-Y', $nextMonth);
        $prevMonthSlug = date('d-m-Y', $prevMonth);

        $nextMonthName = date('F', $nextMonth);
        $prevMonthName = date('F', $prevMonth);

		/* $eventMap is an array of empty event containers
		 * Each array element maps to a day of the month
		 */
		$eventMap = array();
        $currentDay = strtotime("-1 day", $month['start']);

		/* Insert blank days to offset day of week of first day of month
		 */
		$blankStart = array();

		for ($i = 0; $i < $month['first']; $i++) {
			$blankStart[] = '_blank';
		}

		/* Prefill containers for each day of the month
		 */
		for($i = $month['start']; $i < $month['end']; $i += 24 * 3600) {
            $currentDay = strtotime("+1 day", $currentDay);

            $day = array(
				'dayOfMonth' => date('j', $currentDay),
				'niceDay' => date('jS', $currentDay),
				'events' => array(),
            );

            if (date('d-m-Y', $currentDay) == date('d-m-Y', $_SERVER['REQUEST_TIME'])) {
                $day['isToday'] = '1';
            }

			$eventMap[$day['dayOfMonth']] = $day;
		}

		/* Get all the events for this month and fill them into the containers
		 */
		$allEvents = $calendarHandler->getEvents($month['start'], $month['end']);
		$allEvents->sort();

		$iterator = $allEvents->createIterator();

		for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
			$current = $iterator->current();

			$currentEventDay = (int)$current->getStartTime()->getUsingFormat('d');
			if ($current->getStartTime()->getTimeOnly() == '12:00am') {
				$current->setStartTime('');
			}
			if (empty($eventMap[$currentEventDay]['events'])) {
				$eventMap[$currentEventDay]['events'] = array();
			}
			$eventMap[$currentEventDay]['events'][] = $current;
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
		if ($numBlanks < 7 ) {
			for ($i = 0; $i < $numBlanks; $i++) {
				$blankEnd[] = '_blank';
			}
		}

		/* Manufacture $calData into the original structure
		 */
		$calData = array_merge($blankStart, $eventMap, $blankEnd);

        $aside = sprintf('<a href="%s" data-icon="back">%s</a><a href="%s" data-icon="next">%s</a>',
            "/m/calendar/month/$prevMonthSlug", '',
            "/m/calendar/month/$nextMonthSlug", ''
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

    public function Week($date = '') {

        $response = new Response_HTTP();
        $selected = $this->parseDate($date);
        $calFactory = new Calendar_Api_Factory();
        $calendarHandler = $calFactory->produce();

        $dayOfWeek = date("w", mktime(0, 0, 0, $selected['month'], $selected['day'], $selected['year']));
        $startOfWeek = mktime(0, 0, 0, $selected['month'], ($selected['day'] - $dayOfWeek), $selected['year']);
        $endOfWeek = strtotime("+6 days", $startOfWeek);

        if (date("m", $startOfWeek) != date("m", $endOfWeek)) {
            $title = date('jS F', $startOfWeek) . ' - ' . date("jS F", $endOfWeek);
        }
        else {
            $title = date("jS", $startOfWeek) . ' - ' . date('jS F', $endOfWeek);
        }

        $calendar = array();

        for($i = 0; $i < 7; $i++) {
            $currentDay = strtotime("+$i day", $startOfWeek);

            $calendar[] = array(
                'title'   => date('jS F', $currentDay),
                'aside'   => '',
                'class'   => (date('Ymd') == date('Ymd', $currentDay) ? 'cal-today ' : ' ') . 'wide',
                'icon'    => '',
                'content' => $calendarHandler->getDayEvents($currentDay)
            );
        }

        $response->setPage('title','Week Calendar');
        $response->setResponse('articles', $calendar);

        return $response;
    }

    public function Agenda($date = '', $limit = 7) {
        $response = new Response_HTTP();
        $selected = $this->parseDate($date);
        $calFactory = new Calendar_Api_Factory();
        $calendarHandler = $calFactory->produce();

        $dayOfWeek = date("w", mktime(0, 0, 0, $selected['month'], $selected['day'], $selected['year']));
        $startOfWeek = mktime(0, 0, 0, $selected['month'], ($selected['day'] - $dayOfWeek), $selected['year']);

        $events = array();

        for($i = 0; $i < $limit; $i++) {
            $currentDay = strtotime("+$i day", $startOfWeek);
            $dayEvents = $calendarHandler->getDayEvents($currentDay);
            if(is_array($dayEvents)) {
                $dayEvents[0]['header'] = date('jS F', $currentDay);
                foreach($dayEvents as $e) {
                    $events[] = $e;
                }
            }
        }

        $calendar = array(
            'title'   => 'Agenda',
            'aside'   => '<a data-icon="next" href="/m/calendar/agenda/' . date('d-m-Y') . '/m/' . ($limit + 2) . '">more</a>',
            'class'   => '',
            'icon'    => '',
            'content' => $events
        );

        $response->setPage('title','Agenda');
        $response->setResponse('articles', array($calendar));

        return $response;
    }

    protected function parseDate($date) {
        try {
            list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $date);
        }
        catch (Exception $e) {
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

    protected function phoneAgenda($selected) {
        $response = new Response_HTTP();

        $calFactory = new Calendar_Api_Factory();
        $calendarHandler = $calFactory->produce();

        $dayOfWeek = date("w", mktime(0, 0, 0, $selected['month'], $selected['day'], $selected['year']));
        $startOfWeek = mktime(0, 0, 0, $selected['month'], ($selected['day'] - $dayOfWeek), $selected['year']);
        $endOfWeek = strtotime("+6 days", $startOfWeek);

        if (date("m", $startOfWeek) != date("m", $endOfWeek)) {
            $title = date('jS F', $startOfWeek) . ' - ' . date("jS F", $endOfWeek);
        }
        else {
            $title = date("jS", $startOfWeek) . ' - ' . date('jS F', $endOfWeek);
        }

        $calendar = array();

        for($i = 0; $i < 7; $i++) {
            $currentDay = strtotime("+$i day", $startOfWeek);

            $calendar[] = array(
                'title'   => date('jS F', $currentDay),
                'aside'   => '',
                'class'   => date('Ymd') == date('Ymd', $currentDay) ? 'cal-today' : '',
                'icon'    => '',
                'content' => $calendarHandler->getDayEvents($currentDay)
            );
        }

        $response->setPage('title','Week Calendar');
        $response->setResponse('articles', $calendar);

        return $response;
    }


    /**
     * Return a listing of dates with events in them.
     * Depending on $_SESSION['env'], will return a week or a month
     * @param string $date
     * @return Response_HTTP
     */
    public function Display($date = '') {
		$auth = new Authenticator();
		$user = $auth->getSessionUser();

        try {
            list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $date);
        }
        catch (Exception $e) {
            list($selectedDay, $selectedMonth, $selectedYear) = explode('-', date('d-m-Y'));
        }

        $selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);
        if ($selectedDate <= 0) {
            $selectedDate = time();
        }

        $selected['day'] = date("j", $selectedDate);
        $selected['month'] = date("n", $selectedDate);
        $selected['year'] = date("Y", $selectedDate);

        // display month view on tablets, otherwise fall back to the weekly list
        if(isset($_SESSION['env']) && $_SESSION['env']['type'] == ClientDevice::TYPE_TABLET) {
            $response = $this->displayMonth($selected, $user);
        }
        else {
            $response = $this->displayWeek($selected, $user);
        }

        return $response;
    }


    private function displayWeek($selected, $user) {
        $response = new Response_HTTP();

        $calFactory = new Calendar_Api_Factory();
        $calendarHandler = $calFactory->produce();

        $dayOfWeek = date("w", mktime(0, 0, 0, $selected['month'], $selected['day'], $selected['year']));
        $startOfWeek = mktime(0, 0, 0, $selected['month'], ($selected['day'] - $dayOfWeek), $selected['year']);
        $endOfWeek = strtotime("+6 days", $startOfWeek);

        if (date("m", $startOfWeek) != date("m", $endOfWeek)) {
            $title = date('jS F', $startOfWeek) . ' - ' . date("jS F", $endOfWeek);
        }
        else {
            $title = date("jS", $startOfWeek) . ' - ' . date('jS F', $endOfWeek);
        }

        $calendar = array();

        for($i = 0; $i < 7; $i++) {
            $currentDay = strtotime("+$i day", $startOfWeek);

            $calendar[] = array(
                'title'   => date('jS F', $currentDay),
                'aside'   => '',
                'class'   => date('Ymd') == date('Ymd', $currentDay) ? 'cal-today' : '',
                'icon'    => '',
                'content' => $calendarHandler->getDayEvents($currentDay)
            );
        }

        $response->setPage('title','Week Calendar');
        $response->setResponse('articles', $calendar);

        return $response;
    }


    private function displayMonth($selected, $user) {
        $response = new Response_HTTP();

        $calFactory = new Calendar_Api_Factory();
        $calendarHandler = $calFactory->produce();

        $week['day']    = date("w", mktime(0, 0, 0, $selected['month'], $selected['day'], $selected['year']));
        $week['start']  = mktime(0, 0, 0, $selected['month'], ($selected['day'] - $week['day']), $selected['year']);
        $week['end']    = strtotime("+6 days", $week['start']);

        $month['start'] = mktime(0, 0, 0, $selected['month'], 1, $selected['year']);
        $month['first'] = date('w', $month['start']);
        $month['end']   = strtotime("+1 month", $month['start']);

        $nextMonth = mktime(0, 0, 0, $selected['month'] + 1, 1, $selected['year']);
        $prevMonth = mktime(0, 0, 0, $selected['month'] - 1, 1, $selected['year']);

        $nextMonthSlug = date('d-m-Y', $nextMonth);
        $prevMonthSlug = date('d-m-Y', $prevMonth);

        $nextMonthName = date('F', $nextMonth);
        $prevMonthName = date('F', $prevMonth);

        $calData = array();

        $currentDay = strtotime("-1 day", $month['start']);

        // insert blank days to offset day of week of first day of month
        for($i = 0; $i < $month['first']; $i++) {
            $calData[] = '_blank';
        }

        for($i = $month['start']; $i < $month['end']; $i += 24 * 3600) {
            $currentDay = strtotime("+1 day", $currentDay);

            $calData[] = array(
                'day' => date('jS', $currentDay),
                'events' => $calendarHandler->getDayEvents($currentDay)
            );
        }

        // pad out the final line
        for($i = 0; $i < count($calData) % 7; $i++) {
            $calData[] = '_blank';
        }

        $aside = sprintf('<a href="%s" data-icon="back">%s</a><a href="%s" data-icon="next">%s</a>',
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
	 * Return the data for a single event.
	 *
	 * @param $event_id
	 * @param $connector_id
	 *
	 * @return Response_HTTP
	 */
    public function Event($event_id, $connector_id) {

		/* Find out from whence this event came
		 */
		$configRecord = Calendar_Api_Config_Mapper::getInstance()->findById($connector_id);

		/* If the config was found, continue as normal
		 */
		if (is_a($configRecord, 'Calendar_Api_Config_Record')) {
			$config = $configRecord->decodeConfig();
			$config->setId($connector_id);

			$factory = new Calendar_Api_Factory();
			$factory->resetConfigs();
			$factory->addConfig($config);

			$composite = $factory->produce();

			/* Forgive me, for I have sinned
			 */
			$event = $composite->getEvent($event_id);

			/* Standardise into some format that mobile expects
			 */
			if (is_a($event, 'Calendar_Event_Type_Abstract')) {
				$standardisedEvent = array(
					'id' => $event->getEventId(),
					'series_id' => '',
					'owner' => $event->getAuthorId(),
					'access' => $event->getAccess(),
					'sid' => $event->getSid(),
					'type' => $event->getType(),
					'note' => $event->getNote(),
					'date' => $event->getStartTime()->getDatabaseDate(),
					'location' => $event->getLocation(),
					'attendance' =>  method_exists($event, 'getAtendance') ? $event->getAttendance() : '',
					'detail' => $event->getDetail(),
					'publish_date' => $event->getPublishDate(),
					'date' => $event->getStartTime()->getDatabaseDate(),
					'startTime' => $event->getStartTime()->getUsingFormat('h:iA'),
					'endTime' => $event->getEndTime()->getUsingFormat('h:iA'),

					/* The author's title - mr, mrs, etc.
					 */
					'title' => '',
					'firstname' => $event->getAuthorName(),
					'lastname' => '',
				);

				$time = array(
					'title'   => 'mort',
					'aside'   => '',
					'class'   => '',
					'icon'    => 'clock',
					'content' => $standardisedEvent
				);

				$detail = array(
					'title'   => 'Details',
					'aside'   => '',
					'class'   => '',
					'icon'    => 'news',
					'content' => array(
						'detail' => $standardisedEvent['detail']
					)
				);

				$response = new Response_HTTP();
				$response->setPage('title', $standardisedEvent['note']);
				$response->setResponse('articles', array($time, $detail));
			}
	        else {
	            $response = new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
	        }
		}
        else {
			$response = new Response_HTTP(Response_HTTP::HTTP_NOT_FOUND);
		}

        return $response;
    }
}
