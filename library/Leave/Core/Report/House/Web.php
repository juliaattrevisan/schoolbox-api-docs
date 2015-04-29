<?php
/**
 * Leave_Core_Report_House_Web
 *
 * Online house report as found in the Schoolbox
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Leave_Core_Report_House_Web
{
    protected $templateFile = 'modules/leave/report/house/web.html';
    protected $page = null;
    protected $data = array();
    protected $requestList = array();
    protected $houseList = array();
    protected $range = -1;
    protected $combined = array();
    protected $currentHouse = null;

    /* Decorations to use on the little L's etc.
	 * Shared functionality with csv export -_-
	 */
    protected $decoration = array(
        'pendingStart' => "<span class='leave-pending'>",
        'pendingEnd' => "</span><br />",
        'approvedStart' => "<span class='leave-approved'>",
        'approvedEnd' => "</span><br />",
    );

    protected $rangePeriods = array('morning', 'day', 'night');

    // Types of leave directions
    const LEAVING = 1;
    const AWAY = 2;
    const RETURNING = 3;

    /**
	 * Constructor
	 *
	 * @param $page
	 * @param $data
	 * @param $range
	 */
    public function __construct($page, $data, $range)
    {
        $this->setPage($page);
        $this->setData($data);
        $this->setRange($range);

        /* Implied structure
		 */
        $this->setRequestList($data['requestList']);
        $this->setHouseList($data['houseList']);
        $this->setCurrentHouse($data['house']);

        /* Setup hook
		 */
        $this->setup();
    }

    /**
	 * "...with multiple leave entries amalgamated into one"
	 *
	 * Combine multiple leave requests for the same person into one row.
	 * We do this by preprocessing each request and collating the information.
	 */
    protected function recombination()
    {
        $combined = array();

        foreach($this->getRequestList() as $request) {
            /** @var Leave_Request $request */
            $key = $request->getUser()->getId();

            /* Collect general data
			 */
            $combined[$key]['user_id'] = $key;
            $combined[$key]['user_name'] = Scrub::smartQuotes($request->getUser()->formatName());
            $combined[$key]['user_year'] = $request->getUser()->getYear();
            $combined[$key]['user_boarding_status'] = $request->getUser()->getAbbreviatedBoardingStatus();

            $combined[$key]['request_id'][$request->getId()] = $request->getId();
            $combined[$key]['request_status'][$request->getId()] = $request->getStatus(true);
            $combined[$key]['request_host_type'][$request->getId()] = $request->getHostType()->getName();
            $combined[$key]['request_description'][$request->getId()] = $request->getDescription();
            $combined[$key]['request_travel_type'][$request->getId()] = $request->getTravelType()->getName();

            /* Pending leave requests get a css class for the row
			 * (used to decorate the spans)
			 */
            if ($request->isPending()) {
                $combined[$key]['row_css_class'][] = 'leave-pending';
            }
            /* Non-pending requests (authorised requests)
			 */
            else {
                if ($request->getId()) {
                    $combined[$key]['request_authorisation_user_id'][$request->getAuthorisationUser()->getId()]
                        = Scrub::smartQuotes(trim($request->getAuthorisationUser()->formatName()));
                    $combined[$key]['row_css_class'][] = 'leave-approved';
                } else {
                    $combined[$key]['row_css_class'][] = '';
                }
            }

            /* Text color if request is still pending
			 */
            if ($request->isPending()) {
                $decorationStart = $this->getDecorationPendingStart();
                $decorationEnd = $this->getDecorationPendingEnd();
            } else {
                $decorationStart = $this->getDecorationApprovedStart();
                $decorationEnd = $this->getDecorationApprovedEnd();
            }

            /* For each day that the person is on leave within the prescribed period of time, show an
			 * icon. If they are starting or ending their leave on this day, show the time as well
			 *
			 * Render 3 columns each day for 3 days
			 *
                * AM: 10:00:00 to 18:59:59
                * PM: 19:00:00 to 21:59:59
                * Night: 22:00:00 to 9:59:59
			 */
            $data = $this->getData();

            $morningStart = clone $data['reportStart'];
            $morningStart->addHours(10);

            $dayStart = clone $data['reportStart'];
            $dayStart->addHours(19);

            $nightStart = clone $data['reportStart'];
            $nightStart->addHours(22);

            $nightEnd = clone $data['reportStart'];
            $nightEnd->addHours(34);

            $showStartTime = true;

            for ($j = 0; $j < $this->getRange(); $j++) {

                /* If this person is on leave during the morning
				 */
                if ($request->getOccupancy($morningStart, $dayStart)) {

                    /* Check whether next segment is occupied; if not, also show the time
					 */
                    if ($showStartTime) {

                        /* If $showStartTime == true and he's not on leave in the next period
						 * then he left and returned in the same period
						 */
                        if (!$request->getOccupancy($dayStart, $nightStart)) {
                            $requestPeriodMorning = $requestDateStart->getTimeOnly(
                            ) . ' ' . $request->getFinish()->getTimeOnly();
                            $requestPeriodMorningDirection = self::LEAVING;
                        } else {

                            /* Specifically check for this day, to account for multi-week leave
							 */
                            if ($request->getStart()->getDatabaseDate() == $morningStart->getDatabaseDate()) {
                                $requestPeriodMorning = $request->getStart()->getTimeOnly();
                                $requestPeriodMorningDirection = self::LEAVING;
                            } else {
                                $requestPeriodMorning = 'L';
                                $requestPeriodMorningDirection = self::AWAY;
                            }
                        }

                        $showStartTime = false;
                    } else {
                        if (!$showStartTime && !$request->getOccupancy($dayStart, $nightStart)) {
                            $requestPeriodMorning = $request->getFinish()->getTimeOnly();
                            $requestPeriodMorningDirection = self::RETURNING;
                        } else {
                            $requestPeriodMorning = 'L';
                            $requestPeriodMorningDirection = self::AWAY;
                        }
                    }
                }

                /* If this person is on leave during the day
				 */
                if ($request->getOccupancy($dayStart, $nightStart)) {
                    if ($showStartTime) {
                        if (!$request->getOccupancy($nightStart, $nightEnd)) {
                            $requestPeriodDay = $request->getStart()->getTimeOnly(
                            ) . ' ' . $request->getFinish()->getTimeOnly();
                            $requestPeriodDayDirection = self::LEAVING;
                        } else {
                            if ($request->getStart()->getDatabaseDate() == $dayStart->getDatabaseDate()) {
                                $requestPeriodDay = $request->getStart()->getTimeOnly();
                                $requestPeriodDayDirection = self::LEAVING;
                            } else {
                                $requestPeriodDay = 'L';
                                $requestPeriodDayDirection = self::AWAY;
                            }
                        }

                        $showStartTime = false;
                    } else {
                        if (!$showStartTime && !$request->getOccupancy($nightStart, $nightEnd)) {
                            $requestPeriodDay = $request->getFinish()->getTimeOnly();
                            $requestPeriodDayDirection = self::RETURNING;
                        } else {
                            $requestPeriodDay = 'L';
                            $requestPeriodDayDirection = self::AWAY;
                        }
                    }
                }

                /* If this person is on leave during the night
				 */
                if ($request->getOccupancy($nightStart, $nightEnd)) {

                    /* Check whether the next morning is occupied
					 */
                    $nextMorning = $dayStart;
                    $nextMorning->addDays(1);

                    if ($showStartTime) {
                        if (!$request->getOccupancy($nightEnd, $nextMorning)) {
                            $requestPeriodNight = $request->getStart()->getTimeOnly(
                            ) . ' ' . $request->getFinish()->getTimeOnly();
                            $requestPeriodNightDirection = self::LEAVING;
                        } else {
                            if ($request->getStart()->getDatabaseDate() == $nightStart->getDatabaseDate()) {
                                $requestPeriodNight = $request->getStart()->getTimeOnly();
                                $requestPeriodNightDirection = self::LEAVING;
                            } else {
                                $requestPeriodNight = 'L';
                                $requestPeriodNightDirection = self::AWAY;
                            }
                        }

                        $showStartTime = false;
                    } else {
                        if (!$showStartTime && !$request->getOccupancy($nightEnd, $nextMorning)) {
                            $requestPeriodNight = $request->getFinish()->getTimeOnly();
                            $requestPeriodNightDirection = self::RETURNING;

                        } else {
                            $requestPeriodNight = 'L';
                            $requestPeriodNightDirection = self::AWAY;
                        }
                    }
                }

                /* Display icon and time where appropriate
				 */
                $combined[$key]['periods'][$j]['request_period']['morning'][] = array(
                    'display' => $requestPeriodMorning,
                    'direction' => $requestPeriodMorningDirection,
                    'status' => $request->getStatus()
                );
                $combined[$key]['periods'][$j]['request_period']['day'][] = array(
                    'display' => $requestPeriodDay,
                    'direction' => $requestPeriodDayDirection,
                    'status' => $request->getStatus()
                );
                $combined[$key]['periods'][$j]['request_period']['night'][] = array(
                    'display' => $requestPeriodNight,
                    'direction' => $requestPeriodNightDirection,
                    'status' => $request->getStatus()
                );


                /* Iteration reset
				 */
                $morningStart->addDays(1);
                $dayStart->addDays(1);
                $nightStart->addDays(1);
                $nightEnd->addDays(1);

                unset($requestPeriodMorning, $requestPeriodDay, $requestPeriodNight, $requestPeriodMorningDirection, $requestPeriodDayDirection, $requestPeriodNightDirection);
            }
        }

        $this->setCombined($combined);
    }

    /**
	 * Facade method
	 *
	 * Render house report
	 */
    public function render()
    {
        $this->renderHouseList();
        $this->renderHeadings();
        $this->renderRequests();
        $this->renderMetaDetails();
    }

    /**
	 * Set up blocks and stuff
	 */
    protected function setup()
    {
        $this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
        $this->getPage()->setBlock(TEMPLATE_content, 'house', 'house_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'no_requests', 'no_requests_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'row', 'row_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'request_period_total', 'request_period_total_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'report_header', 'report_header_block');

        $this->getPage()->setBlock(TEMPLATE_content, 'report_header_subtitle', 'report_header_subtitle_block');
        $this->getPage()->setBlock('report_header_subtitle', 'report_header_subtitle_show_am', 'report_header_subtitle_show_am_block');
        $this->getPage()->setBlock('report_header_subtitle', 'report_header_subtitle_show_day', 'report_header_subtitle_show_day_block');
        $this->getPage()->setBlock('report_header_subtitle', 'report_header_subtitle_show_pm', 'report_header_subtitle_show_pm_block');
        $this->getPage()->setBlock('report_header_subtitle', 'report_header_subtitle_hide_am', 'report_header_subtitle_hide_am_block');
        $this->getPage()->setBlock('report_header_subtitle', 'report_header_subtitle_hide_day', 'report_header_subtitle_hide_day_block');

        $this->getPage()->setBlock('row', 'request', 'request_block');
        $this->getPage()->setBlock('row', 'empty_request', 'empty_request_block');
        $this->getPage()->setBlock('request', 'request_period', 'request_period_block');
        $this->getPage()->setBlock('request', 'request_authorisation', 'request_authorisation_block');

        $this->getPage()->setBlock('request_period', 'request_period_show_am', 'request_period_show_am_block');
        $this->getPage()->setBlock('request_period', 'request_period_show_day', 'request_period_show_day_block');
        $this->getPage()->setBlock('request_period', 'request_period_show_pm', 'request_period_show_pm_block');
        $this->getPage()->setBlock('request_period', 'request_period_hide_am', 'request_period_hide_am_block');
        $this->getPage()->setBlock('request_period', 'request_period_hide_day', 'request_period_hide_day_block');

        /* Initial state
		 */
        $this->getPage()->setVar('no_requests_block', '');
        $this->getPage()->setVar('row_block', '');

        /* Execute data recombination
		 */
        $this->recombination();
    }

    /**
	 * Render leave requests
	 */
    protected function renderRequests()
    {
        if (count($this->getRequestList())) {
            $allPeople = $this->getCombined();
            $totalPeople = count($allPeople);

            // Create base totals for all ranges and periods
            for ($j = 0; $j < $this->getRange(); $j++) {
                $totals[] = array('morning' => $totalPeople, 'day' => $totalPeople, 'night' => $totalPeople);
            }

            if (is_array($allPeople) && $totalPeople) {
                foreach($allPeople as $currentPerson) {
                    $hostTypes = array();

                    foreach($currentPerson['request_host_type'] as $currentHostType) {
                        if (!empty($currentHostType)) {
                            $hostTypes[] = $currentHostType;
                        }
                    }

                    $this->getPage()->setVar(array(
                        'USER_ID' => $currentPerson['user_id'],
                        'USER_NAME' => $currentPerson['user_name'],
                        'USER_YEAR' => $currentPerson['user_year'],
                        'USER_BOARDING_STATUS' => $currentPerson['user_boarding_status'],
                        'REQUEST_STATUS' => implode('<hr />', $currentPerson['request_status']),
                        'REQUEST_HOST' => implode('<hr />', $hostTypes),
                        'REQUEST_ACTIVITY' => implode('<hr />', $currentPerson['request_description']),
                        'CLASS' => implode(' ', $currentPerson['row_css_class']),
                    ));

                    /* Render out each authorisation user -_-
					 */
                    if (is_array($currentPerson['request_authorisation_user_id']) && count($currentPerson['request_authorisation_user_id'])) {
                        foreach($currentPerson['request_authorisation_user_id'] as $id => $name) {
                            $this->getPage()->setVar(array(
                                'REQUEST_AUTHORISATION_USER_ID' => $id,
                                'REQUEST_AUTHORISATION_USER_NAME' => $name,
                            ));

                            $this->getPage()->parse('request_authorisation_block', 'request_authorisation', true);
                        }
                    } else {
                        $this->getPage()->setVar('request_authorisation_block', '');
                    }

                    /* Used to hide the first two blocks
					 */
                    $i = 0;

                    foreach($currentPerson['periods'] as $currentPeriod) {

                        foreach ($this->rangePeriods as $section) {

                            $requestOutput = '';
                            $removeFromTotal = false;

                            foreach ($currentPeriod['request_period'][$section] as $request) {
                                if ($request['status'] == Leave_Request::STATUS_PENDING && $request['display']) {
                                    $requestOutput .= $this->getDecorationPendingStart(
                                    ) . $request['display'] . $this->getDecorationPendingEnd();

                                } else {
                                    if ($request['status'] == Leave_Request::STATUS_APPROVED && $request['display']) {
                                        $requestOutput .= $this->getDecorationApprovedStart(
                                        ) . $request['display'] . $this->getDecorationApprovedEnd();

                                        if ($request['direction'] == self::AWAY || $request['direction'] == self::RETURNING) {
                                            $removeFromTotal = true;
                                        }
                                    }
                                }
                            }
                            $this->getPage()->setVar('REQUEST_PERIOD_' . strtoupper($section), $requestOutput);
                            $removeFromTotal ? $totals[$i][$section]-- : false;


                        }


                        if (!$i) {
                            $this->getPage()->setVar('request_period_show_am_block', '');
                            $this->getPage()->parse('request_period_show_day_block', 'request_period_show_day');
                            $this->getPage()->parse('request_period_hide_am_block', 'request_period_hide_am');
                            $this->getPage()->setVar('request_period_hide_day_block', '');
                        } else {
                            $this->getPage()->parse('request_period_show_am_block', 'request_period_show_am');
                            $this->getPage()->parse('request_period_show_day_block', 'request_period_show_day');
                            $this->getPage()->setVar('request_period_hide_am_block', '');
                            $this->getPage()->setVar('request_period_hide_day_block', '');
                        }

                        $this->getPage()->parse('request_period_show_pm_block', 'request_period_show_pm');
                        $this->getPage()->parse('request_period_block', 'request_period', true);

                        /* Clear children after parsing out the parent
						 */
                        $this->getPage()->setVar('request_period_show_am_block', '');
                        $this->getPage()->setVar('request_period_show_day_block', '');
                        $this->getPage()->setVar('request_period_show_pm_block', '');
                        $this->getPage()->setVar('request_period_hide_am_block', '');
                        $this->getPage()->setVar('request_period_hide_day_block', '');


                        $i++;


                    }

                    $this->getPage()->setVar('empty_request_block', '');
                    $this->getPage()->parse('request_block', 'request');
                    $this->getPage()->parse('row_block', 'row', true);

                    /* Clear children after parsing out the parent
					 */
                    $this->getPage()->setVar('request_block', '');
                    $this->getPage()->setVar('request_period_block', '');
                    $this->getPage()->setVar('request_authorisation_block', '');
                }
            }
        }
        /* This condition never occurs because of left join on users.
		 */
        else {
            $this->getPage()->parse('no_requests_block', 'no_requests');
            $this->getPage()->setVar('row_block', '');
        }

        /* Output totals */
        for ($j = 0; $j < $this->getRange(); $j++) {
            foreach ($this->rangePeriods as $period) {
                if (!($j == 0 && $period == 'morning')) {
                    $this->getPage()->setVar('PERIOD_TOTAL', $totals[$j][$period]);
                    $this->getPage()->parse('request_period_total_block', 'request_period_total', true);
                }
            }
        }
    }

    /**
	 * Render headings for each day in the specified range
	 */
    protected function renderHeadings()
    {
        $data = $this->getData();

        if (count($this->getRequestList())) {

            /* Parse out the headings
			 */
            for ($i = 0; $i < $this->getRange(); $i++) {
                $headerDate = $data['reportStart'];
                $headerDate->addDays($i);

                $this->getPage()->setVar(array(
                    'REPORT_HEADER_WEEKDAY' => $headerDate->getUsingFormat('l'),
                    'REPORT_HEADER_DATE' => $headerDate->getLocalShort(),
                ));

                /* Hide the first two columns of the first group
				 */
                if (!$i) {
                    $this->getPage()->setVar('report_header_subtitle_show_am_block', '');
                    $this->getPage()->parse('report_header_subtitle_show_day_block', 'report_header_subtitle_show_day');
                    $this->getPage()->parse('report_header_subtitle_hide_am_block', 'report_header_subtitle_hide_am');
                    $this->getPage()->setVar('report_header_subtitle_hide_day_block', '');
                } else {
                    $this->getPage()->parse('report_header_subtitle_show_am_block', 'report_header_subtitle_show_am');
                    $this->getPage()->parse('report_header_subtitle_show_day_block', 'report_header_subtitle_show_day');
                    $this->getPage()->setVar('report_header_subtitle_hide_am_block', '');
                    $this->getPage()->setVar('report_header_subtitle_hide_day_block', '');
                }

                $this->getPage()->parse('report_header_subtitle_show_pm_block', 'report_header_subtitle_show_pm');

                $this->getPage()->parse('report_header_block', 'report_header', true);
                $this->getPage()->parse('report_header_subtitle_block', 'report_header_subtitle', true);

                /* Clear children after parsing out the parent
				 */
                $this->getPage()->setVar('report_header_subtitle_show_am_block', '');
                $this->getPage()->setVar('report_header_subtitle_show_day_block', '');
                $this->getPage()->setVar('report_header_subtitle_show_pm_block', '');
                $this->getPage()->setVar('report_header_subtitle_hide_am_block', '');
                $this->getPage()->setVar('report_header_subtitle_hide_day_block', '');
            }
        } else {
            $this->getPage()->setVar('report_header_block', '');
            $this->getPage()->setVar('report_header_subtitle_block', '');
        }
    }

    /**
	 * Render the house list dropdown selector
	 */
    protected function renderHouseList()
    {
        if (count($this->getHouseList())) {
            foreach ($this->getHouseList() as $house) {
                $this->getPage()->setVar(array(
                    'HOUSE_ID' => $house->getId(),
                    'HOUSE_NAME' => Scrub::text($house->getHouse())
                ) );

                if ($house->getId() == $this->getCurrentHouse()->getId()) {
                    $this->getPage()->setVar('HOUSE_SELECTED', 'selected="selected"');
                } else {
                    $this->getPage()->setVar('HOUSE_SELECTED', '');
                }

                $this->getPage()->parse('house_block', 'house', true);
            }
        }
    }

    /**
	 * Render extra information about the report
	 */
    protected function renderMetaDetails()
    {
        $currentTime = new Alaress_Date_Format(time(), 'U');

        $this->getPage()->setVar(array(
            'HOUSE_NAME' => Scrub::text($this->getCurrentHouse()->getHouse()),
            'HOUSE_ID' => $this->getCurrentHouse()->getId(),
            'GENERATED_AT' => $currentTime->getShort(),
            'REPORT_DATE' => $this->getReportDate(),
        ));
    }

    /* Getters/Setters
	 */

    /**
	 * Get report date
	 *
	 * @return mixed
	 */
    protected function getReportDate()
    {
        return $this->data['date'];
    }

    /**
	 * Get report start date
	 *
	 * @return mixed
	 */
    protected function getReportStart()
    {
        return $this->data['reportStart'];
    }

    /**
	 * Set page
	 *
	 * @param $page
	 */
    protected function setPage($page)
    {
        $this->page = $page;
    }

    /**
	 * Get page
	 *
	 * @return null
	 */
    protected function getPage()
    {
        return $this->page;
    }

    /**
	 * Set data
	 *
	 * @param $data
	 */
    protected function setData($data)
    {
        $this->data = $data;
    }

    /**
	 * Get data
	 *
	 * @return mixed
	 */
    protected function getData()
    {
        return $this->data;
    }

    /**
	 * Set range
	 *
	 * @param $range
	 */
    protected function setRange($range)
    {
        $this->range = $range;
    }

    /**
	 * Get range
	 *
	 * @return mixed
	 */
    protected function getRange()
    {
        return $this->range;
    }

    /**
	 * Set request list
	 *
	 * @param $requestList
	 */
    protected function setRequestList($requestList)
    {
        $this->requestList = $requestList;
    }

    /**
	 * Get request list
	 *
	 * @return mixed
	 */
    protected function getRequestList()
    {
        return $this->requestList;
    }

    /**
	 * Set combined result
	 *
	 * @param $combined
	 */
    protected function setCombined($combined)
    {
        $this->combined = $combined;
    }

    /**
	 * Get combined result
	 *
	 * @return array
	 */
    protected function getCombined()
    {
        return $this->combined;
    }

    /**
	 * Set house list
	 *
	 * @param $houseList
	 */
    protected function setHouseList($houseList)
    {
        $this->houseList = $houseList;
    }

    /**
	 * Get house list
	 *
	 * @return array
	 */
    protected function getHouseList()
    {
        return $this->houseList;
    }

    /**
	 * Set current house
	 *
	 * @param $currentHouse
	 */
    protected function setCurrentHouse($currentHouse)
    {
        $this->currentHouse = $currentHouse;
    }

    /**
	 * Get current house
	 *
	 * @return null
	 */
    protected function getCurrentHouse()
    {
        return $this->currentHouse;
    }

    /**
	 * Get start of pending decoration
	 *
	 * @return mixed
	 */
    protected function getDecorationPendingStart()
    {
        return $this->decoration['pendingStart'];
    }

    /**
	 * Get end of pending decoration
	 *
	 * @return mixed
	 */
    protected function getDecorationPendingEnd()
    {
        return $this->decoration['pendingEnd'];
    }

    /**
	 * Get start of approved decoration
	 *
	 * @return mixed
	 */
    protected function getDecorationApprovedStart()
    {
        return $this->decoration['approvedStart'];
    }

    /**
	 * Get end of approved decoration
	 *
	 * @return mixed
	 */
    protected function getDecorationApprovedEnd()
    {
        return $this->decoration['approvedEnd'];
    }

    /**
	 * Get template file
	 *
	 * @return string
	 */
    protected function getTemplateFile()
    {
        return $this->templateFile;
    }
}
