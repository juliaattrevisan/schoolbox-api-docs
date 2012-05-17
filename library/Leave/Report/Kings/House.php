<?php
/**
 * Leave_Report_Kings_House
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Leave_Report_Kings_House {
	private $page = null;
	private $data = array();
	private $requestList = array();
	private $range = -1;
	private $combined = array();

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $data
	 * @param $requestList
	 * @param $range
	 */
	public function __construct($page, $data, $requestList, $range) {
		$this->setPage($page);
		$this->setData($data);
		$this->setRequestList($requestList);
		$this->setRange($range);
	}

	/**
	 * "...with multiple leave entries amalgamated into one"
	 */
	public function recombination() {
		/* Combine multiple leave requests for the same person into one row.
		 * We do this by preprocessing each request and collating the information.
		 */
		$combined = array();

		foreach($this->getRequestList() as $i => $request) {
			if ($request->getId()) {

				/* Text color if request is still pending
				 */
				if ($request->isPending()) {
					$pendingTextClass = 'grey';
				}
				else {
					$pendingTextClass = '';
				}

				$key = $request->getUser()->getId();

				/* Collect general data
				 */
				$combined[$key]['user_id'] = $key;
				$combined[$key]['user_name'] = $request->getUser()->getName();
				$combined[$key]['user_year'] = $request->getUser()->getYear();
				$combined[$key]['user_boarding_status'] = $request->getUser()->getAbbreviatedBoardingStatus();

				$combined[$key]['request_id'][$request->getId()] = $request->getID();
				$combined[$key]['request_status'][$request->getId()] = $request->getStatus(true);
				$combined[$key]['request_host_type'][$request->getId()] = $request->getHostType(true);
				$combined[$key]['request_description'][$request->getId()] = $request->getDescription();

				if ($request->isPending()) {
					$combined[$key]['request_authorisation_user_id'][$request->getId()] = '';
					$combined[$key]['request_authorisation_user_name'][$request->getId()] = '';
					$combined[$key]['row_css_class'][] = 'leave-pending';
				}
				else {
					$combined[$key]['request_authorisation_user_id'][$request->getId()] = $request->getAuthorisationUser()->getId();
					$combined[$key]['request_authorisation_user_name'][$request->getId()] = $request->getAuthorisationUser()->getName();
					$combined[$key]['row_css_class'][] = '';
				}

				/* Start and end of leave
				 */
				$requestDateStart = new Alaress_Date_Format($request['start'], 'y-m-d h:i:s');
				$requestDateFinish = new Alaress_Date_Format($request['finish'], 'y-m-d h:i:s');

				/* For each day that the person is on leave within the prescribed period of time, show an
				 * icon. If they are starting or ending their leave on this day, show the time as well
				 *
				 * Render 3 columns each day for 3 days
				 *
				 * Morning: 00:00:00 to 09:59:59
				 * Day: 10:00:00 to 18:59:59
				 * Night: 19:00:00 to 21:59:59
				 */
				$data = $this->getData();

				$morningStart = new Alaress_Date_Format($data['reportStart']->getUnixTimestamp(), 'U');
				$dayStart = new Alaress_Date_Format($morningStart->getUnixTimestamp() + 10*60*60-1, 'U');
				$nightStart = new Alaress_Date_Format($dayStart->getUnixTimestamp() + 9*60*60, 'U');
				$nightEnd = new Alaress_Date_Format($nightStart->getUnixTimestamp() + 3*60*60, 'U');

				$showStartTime = true;

				for ($j = 0; $j < $this->getRange(); $j++) {

					/* If this person is on leave during the morning
					 */
					if ($request->getOccupancy($morningStart, $dayStart)) {

						/* Check whether next segment is occupied; if not, also show the time
						 */
						if ($showStartTime) {

							/* Specifically check for this day, to account for multi-week leave
							 */
							if ($requestDateStart->getDatabaseDate() == $morningStart->getDatabaseDate()) {
								$requestPeriodMorning = "<span class='$pendingTextClass'>{$requestDateStart->getTimeOnly()}</span>";
							}
							else {
								$requestPeriodMorning = "<span class='$pendingTextClass'>L</span>";
							}

							$showStartTime = false;
						}
						else if (!$showStartTime && !$request->getOccupancy($dayStart, $nightStart)) {
							$requestPeriodMorning = "<span class='$pendingTextClass'>{$requestDateFinish->getTimeOnly()}</span>";
						}
						else {
							$requestPeriodMorning = "<span class='$pendingTextClass'>L</span>";
						}
					}

					/* If this person is on leave during the day
					 */
					if ($request->getOccupancy($dayStart, $nightStart)) {
						if ($showStartTime) {
							if ($requestDateStart->getDatabaseDate() == $dayStart->getDatabaseDate()) {
								$requestPeriodDay = "<span class='$pendingTextClass'>{$requestDateStart->getTimeOnly()}</span>";
							}
							else {
								$requestPeriodDay = "<span class='$pendingTextClass'>L</span>";
							}

							$showStartTime = false;
						}
						else if (!$showStartTime && !$request->getOccupancy($nightStart, $nightEnd)) {
							$requestPeriodDay = "<span class='$pendingTextClass'>{$requestDateFinish->getTimeOnly()}</span>";
						}
						else {
							$requestPeriodDay = "<span class='$pendingTextClass'>L</span>";
						}
					}

					/* If this person is on leave during the night
					 */
					if ($request->getOccupancy($nightStart, $nightEnd)) {

						/* Check whether the next morning is occupied
						 */
						$nextMorning = new Alaress_Date_Format($dayStart->getDatabase(), 'y-m-d h:i:s');
						$nextMorning->addDays(1);

						if ($showStartTime) {
							if ($requestDateStart->getDatabaseDate() == $nightStart->getDatabaseDate()) {
								$requestPeriodNight = "<span class='$pendingTextClass'>{$requestDateStart->getTimeOnly()}</span>";
							}
							else {
								$requestPeriodNight = "<span class='$pendingTextClass'>L</span>";
							}

							$showStartTime = false;
						}
						else if (!$showStartTime && !$request->getOccupancy($nightEnd, $nextMorning)) {
							$requestPeriodNight = "<span class='$pendingTextClass'>{$requestDateFinish->getTimeOnly()}</span>";
						}
						else {
							$requestPeriodNight = "<span class='$pendingTextClass'>L</span>";
						}
					}

					/* Display icon and time where appropriate
					 */
					$combined[$key]['periods'][$j]['request_period_morning'] .= $requestPeriodMorning;
					$combined[$key]['periods'][$j]['request_period_day'] .= $requestPeriodDay;
					$combined[$key]['periods'][$j]['request_period_night'] .= $requestPeriodNight;

					/* Iteration reset
					 */
					$morningStart->addDays(1);
					$dayStart->addDays(1);
					$nightStart->addDays(1);
					$nightEnd->addDays(1);

					unset($requestPeriodMorning, $requestPeriodDay, $requestPeriodNight);
				}
			}
		}

		$this->setCombined($combined);
	}

	/**
	 * Render house report
	 */
	public function render() {
		$allPeople = $this->getCombined();

		if (is_array($allPeople) && count($allPeople)) {
			foreach($allPeople as $currentPerson) {
				$this->getPage()->setVar(array(
					'USER_ID' => $currentPerson['user_id'],
					'USER_NAME' => $currentPerson['user_name'],
					'USER_YEAR' => $currentPerson['user_year'],
					'USER_BOARDING_STATUS' => $currentPerson['user_boarding_status'],

					'REQUEST_STATUS' => implode('<hr />', $currentPerson['request_status']),
					'REQUEST_HOST_TYPE' => implode('<hr />', $currentPerson['request_host_type']),
					'REQUEST_DESCRIPTION' => implode('<hr />', $currentPerson['request_description']),

					'REQUEST_AUTHORISATION_USER_ID' => implode('<hr />', $currentPerson['request_authorisation_user_id']),
					'REQUEST_AUTHORISATION_USER_NAME' => implode('<hr />', $currentPerson['request_authorisation_user_name']),

					'CLASS' => implode(' ', $currentPerson['row_css_class']),
				));

				/* Used to hide the first two blocks
				 */
				$i = 0;

				foreach($currentPerson['periods'] as $currentPeriod) {
					$this->getPage()->setVar(array(
						'REQUEST_PERIOD_MORNING' => $currentPeriod['request_period_morning'],
						'REQUEST_PERIOD_DAY' => $currentPeriod['request_period_day'],
						'REQUEST_PERIOD_NIGHT' => $currentPeriod['request_period_night'],
					));

					if (!$i) {
						$this->getPage()->setVar('request_period_show_am_block', '');
						$this->getPage()->setVar('request_period_show_day_block', '');
						$this->getPage()->parse('request_period_hide_am_block', 'request_period_hide_am');
						$this->getPage()->parse('request_period_hide_day_block', 'request_period_hide_day');
					}
					else {
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

				$this->getPage()->parse('row_block', 'row', true);

				$this->getPage()->setVar('empty_request_block', '');
				$this->getPage()->parse('request_block', 'request');

				/* Clear children after parsing out the parent
				 */
				$this->getPage()->setVar('request_period_block', '');
			}
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set page
	 *
	 * @param $page
	 */
	private function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	private function getPage() {
		return $this->page;
	}

	/**
	 * Set data
	 *
	 * @param $data
	 */
	private function setData($data) {
		$this->data = $data;
	}

	/**
	 * Get data
	 *
	 * @return mixed
	 */
	private function getData() {
		return $this->data;
	}

	/**
	 * Set range
	 *
	 * @param $range
	 */
	private function setRange($range) {
		$this->range = $range;
	}

	/**
	 * Get range
	 *
	 * @return mixed
	 */
	private function getRange() {
		return $this->range;
	}

	/**
	 * Set request list
	 *
	 * @param $requestList
	 */
	private function setRequestList($requestList) {
		$this->requestList = $requestList;
	}

	/**
	 * Get request list
	 *
	 * @return mixed
	 */
	private function getRequestList() {
		return $this->requestList;
	}

	/**
	 * Set combined result
	 *
	 * @param $combined
	 */
	private function setCombined($combined) {
		$this->combined = $combined;
	}

	/**
	 * Get combined result
	 *
	 * @return array
	 */
	private function getCombined() {
		return $this->combined;
	}
}