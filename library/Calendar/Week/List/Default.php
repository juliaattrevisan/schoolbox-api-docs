<?php
/**
 * Calendar_Week_List_Default
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Week_List_Default {
	private $calendarHandler = null;
	private $groupHandler = null;
	private $subStuHandler = null;
	private $page = null;
	private $date = '';
	private $categoryList = array();
	private $templateFile = '';
	private $selected = array();
	private $selectedDate = null;
	private $startOfWeek = null;

	/* subout?
 	 */
	private $subout = null;

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $date
	 */
	public function __construct($page, $date) {
		$factory = new Calendar_Bridge_Factory();
		$api = $factory->produce();

		$this->setCalendarHandler($api);

		$this->setGroupHandler(new Group());
		$this->setSubStuHandler(new SubmissionStudent());
		$this->setPage($page);
		$this->setDate($date);
		$this->setCategoryList($this->getGroupHandler()->involvedInCategoryList($_SESSION['id']));

		$this->setupTemplateFile();
		$this->setupBlocks();
	}

	/**
	 * Choose template file
	 */
	private function setupTemplateFile() {
		$this->setTemplateFile('calendar/week-list.html');
	}

	/**
	 * Set up blocks and stuff
	 */
	private function setupBlocks() {
		$this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
		$this->getPage()->setTab("calendar");
		$this->getPage()->setTitle('Calendar Week');

		$this->getPage()->setBlock(TEMPLATE_content, 'day', 'day_block');
		$this->getPage()->setBlock("day", "note", "note_block");
		$this->getPage()->setBlock("note", "time", "time_block");
		$this->getPage()->setBlock("note", "attendance_icon", "attendance_icon_block");
		$this->getPage()->setBlock("note", "attendance_status", "attendance_status_block");
		$this->getPage()->setBlock("note", "attendance_request_button", "attendance_request_button_block");
		$this->getPage()->setBlock("note", "attendance_list_button", "attendance_list_button_block");
		$this->getPage()->setBlock('note', 'has_category', 'has_category_block');

		/* Initialise selectedDay, selectedMonth, selectedYear
		 * Initialise movement buttons
		 */
		$this->setupSelectedDayMonthYear();
		$this->setupMovementButtons();
	}

	/**
	 * Set up selected day/month/year
	 */
	private function setupSelectedDayMonthYear() {
		list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $this->getDate());

		$selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);

		$this->setSelectedDate($selectedDate);

		if ($selectedDate <= 0) {
			$selectedDate = time();
		}

		$selectedDay = date("j", $selectedDate);
		$selectedMonth = date("n", $selectedDate);
		$selectedYear = date("Y", $selectedDate);

		$dayOfWeek = date("w", mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear));
		$startOfWeek = mktime(0, 0, 0, $selectedMonth, ($selectedDay - $dayOfWeek), $selectedYear);
		$endOfWeek = strtotime("+6 days", $startOfWeek);

		if (date("m", $startOfWeek) != date("m", $endOfWeek)) {
			$month = date('jS F', $startOfWeek) . ' - ' . date("jS F", $endOfWeek);
		}
		else {
			$month = date("jS", $startOfWeek) . ' - ' . date('jS F', $endOfWeek);
		}

		$this->getPage()->setVar(array(
			'SELECTED_DAY' => $selectedDay,
			'SELECTED_MONTH' => $selectedMonth,
			'SELECTED_YEAR' => $selectedYear,
			'YEAR' => date("Y", $startOfWeek),
			'MONTH' => $month,
			'DAY' => $selectedDay,
		));

		$this->setStartOfWeek($startOfWeek);
	}

	/**
	 * Setup movement buttons
	 */
	private function setupMovementButtons() {
		if (isset($_SESSION['calendarFilter'])) {
			$selected['group'] = $_SESSION['calendarFilter']['groups'];
			$selected['campus'] = $_SESSION['calendarFilter']['campus'];
			$selected['event_type'] = $_SESSION['calendarFilter']['events'];
		}
		else {
			if (count($this->getCategoryList())) {
				foreach($this->getCategoryList() as $cat) {
					$selected['group'][] = $cat['id'];
				}
			}

			$selected['campus'] = $_SESSION['campus'];

			$typeList = $this->getCalendarHandler()->getTypeList();

			if (count($typeList)) {
				foreach($typeList as $type) {
					$selected['event_type'][] = $type['eventTypeID'];
				}
			}
		}

		$this->setSelected($selected);

		$this->getPage()->checkList('group', $this->getCategoryList(), $selected['group'], TEMPLATE_content, array('name' => 'GROUP_NAME', 'id' => 'GROUP_ID'));
		$this->getPage()->checkList('campus', $this->getCalendarHandler()->getFilterCampusList($_SESSION['id']), $selected['campus']);
		$this->getPage()->checkList('event_type', $this->getCalendarHandler()->getTypeList(), $selected['event_type'], TEMPLATE_content, array('eventType' => 'EVENT_TYPE_NAME', 'eventTypeID' => 'EVENT_TYPE_ID', 'eventColour' => 'EVENT_TYPE_COLOUR'));
		$this->getPage()->setVar('BACK', date("j-n-Y", strtotime("-1 week", $this->getSelectedDate())));
		$this->getPage()->setVar('FORWARD', date("j-n-Y", strtotime("+1 week", $this->getSelectedDate())));
	}

	/**
	 * Render submission events
	 *
	 * @param $currentDay
	 * @return int
	 */
	private function renderSubmissionEvents($currentDay) {
		$submissionList = $this->getCalendarHandler()->getSubmissionEvents($currentDay);

	    if (is_array($submissionList)) {
	        foreach ($submissionList as $submission) {

	            /* Clear attendance blocks */
	            $this->getPage()->setVar(array(
	                'attendance_icon_block' => '',
	                'attendance_status_block' => '',
	                'attendance_request_button_block' => '',
	                'attendance_list_button_block' => '',
	            ));

	            /* Get status to set action */
	            if ($this->getGroupHandler()->isStudent($_SESSION['id'])) {
	                $action = 'View Details';
	                $this->getPage()->setVar('MODIFY', '<a class="btn go-to float-left" href="/submission/submissionHistory/' . $submission['id'] . '"><span></span>' . $action . '</a>');

	            } elseif ($this->getGroupHandler()->isStaff($_SESSION['id'])) {
	                $this->getPage()->setVar('MODIFY', '<a class="btn go-to float-left" href="/submission/viewSubmission/' . $submission['id'] . '"><span></span>View Submissions</a>');
	            } else {
	                $this->getPage()->setVar('MODIFY', '');
	            }

	            $userHandler = new User();

	            $output = array(
	                'TIME' => $submission['date']->format('g:iA') != '11:59PM'? $submission['date']->format('g:iA') : '', // g:i a
	                'NOTE' => $submission['category'] . (!empty($submission['synergyCode']) ? ' (' . $submission['synergyCode'] . ')' : '') . '<br />' . $submission['name'],
	                'COLOUR' => $this->getCalendarHandler()->displayColour(4),
	                'LOCATION' => '',
	                'DETAIL' => '',
	                'EVENT_TYPE' => 'submission',
	                'TYPE' => 'Due Work',
	                'AUTHOR' => $userHandler->formatName($submission),
	                'AUTHOR_ID' => $submission['owner'],
	                'EVENT_LEVEL' => '',
	                'has_category_block' => '',
	            );

	            $this->getPage()->setVar($output);

				if ($submission['date']->format('g:iA') != '11:59PM') {
					$this->getPage()->parse('time_block', 'time');
				}
				else {
					$this->getPage()->setVar('time_block', '');
				}

				$this->getPage()->parse("note_block", "note", true);

	            $this->setSubout(1);
	        }
	    }
	}

	/**
	 * Render student birthdays
	 *
	 * @param $currentDay
	 */
	private function renderStudentBirthdays($currentDay) {
		if (Cfg::get('site_location') == "mgs" && Cfg::get('external_enable') && $this->getGroupHandler()->isStaff($_SESSION['id'])) {
			$birthdayList = $this->getCalendarHandler()->getStudentBirthdays($currentDay);

			if (is_array($birthdayList) && in_array(3, $_SESSION['campus'])) {
				foreach ($birthdayList as $birthday) {

					/* Clear attendance blocks
					 */
					$this->setVar(array(
						'attendance_icon_block' => '',
						'attendance_status_block' => '',
						'attendance_request_button_block' => '',
						'attendance_list_button_block' => '',
						'has_category_block', '',
					));

					$output = array(
						'TIME' => '',
						'NOTE' => 'Student Birthday - ' . $birthday['name'] . ' in ' . $birthday['form'] . ' turns ' . $birthday['age'],
						'COLOUR' => $this->getCalendarHandler()->displayColour(7),
						'MODIFY' => "",
						'LOCATION' => '',
						'DETAIL' => '',
						'EVENT_TYPE' => 'type7', //filter in mgs lookup table...
						'TYPE' => 'Birthday',
						'AUTHOR' => '',
						'AUTHOR_ID' => '',
						'EVENT_LEVEL' => '',
					);

					$this->getPage()->setVar($output);
					$this->getPage()->setVar('time_block', '');
					$this->getPage()->parse("note_block", "note", true);

					$this->setSubout(1);
				}
			}
		}
	}

	/**
	 * Render day events
	 *
	 * @param $currentDay
	 */
	private function renderDayEvents($currentDay) {
		$eventList = $this->getCalendarHandler()->getDayEvents($currentDay);

		if (is_array($eventList)) {
			$userHandler = new User();

			foreach ($eventList as $event) {
				$this->getPage()->setVar(array(
					'attendance_icon_block' => '',
					'attendance_status_block' => '',
					'attendance_request_button_block' => '',
					'attendance_list_button_block' => '',
				));

				/* If the Publish Date hasn't been reached yet, only show the events to Superusers
				 * in a different way so they can still edit/click them. Otherwie, they don't appear
				 * at all for everyone else
				 */
				$publishDate = new Alaress_Date_Format($event['publish_date'], 'y-m-d h:i:s');

				/* Publish date in the future
		  		 */
				if ($publishDate->getUnixTimestamp() >= time()) {
					if ($this->getGroupHandler()->isSuperuser()) {
						$cssClass = 'translucent';
					}
					else {
						continue;
					}
				}
				else {
					$cssClass = '';
				}

				/* Render event
		  		 */
				$output = array(
					'EVENT_ID' => $event['id'],
					'TIME' => rtrim(
						($event['startTime'] != "12:00AM" ? $event['startTime'] : "") . " - " .
						($event['endTime'] != "12:00AM" ? $event['endTime'] : ""),
						"- "),
					'NOTE' => Scrub::html($event['note']),
					'COLOUR' => $this->getCalendarHandler()->displayColour($event['type']),
					'MODIFY' => $this->getCalendarHandler()->getPerm($event['id']) > 1 ? '<a class="btn modify float-left" href="/calendar/modify.php?eventID=' . $event['id'] . '&returnTo=month"><span></span>Modify</a>' : '',
					'LOCATION' => $event['location'],
					'DETAIL' => $event['detail'],
					'EVENT_TYPE' => 'type' . $event['type'],
					'TYPE' => $event['eventType'],
					'AUTHOR' => $userHandler->formatName($event),
					'AUTHOR_ID' => $event['owner'],
					'EVENT_VISIBLE' => $cssClass,
				);

				if ($event['access'] == 3) {
					if (stristr($event['campusList'], ',')) {
						foreach (explode(',', $event['campusList']) as $campus) {
							$output['EVENT_LEVEL'] .= ' campus' . $campus;
						}
					}
					else {
						$output['EVENT_LEVEL'] .= ' campus' . $event['campusList'];
					}

				}
				else {
					if ($event['access'] == 2) {
						$output['EVENT_LEVEL'] = 'group' . $event['sid'];
					}
					else {
						$output['EVENT_LEVEL'] = 'personal';
					}
				}

				$this->getPage()->setVar($output);

				if ($output['TIME']) {
					$this->getPage()->parse('time_block', 'time');
				}
				else {
					$this->getPage()->setVar('time_block', '');
				}

				/* Attendance */

				if ($event['attendance']) {
					$this->getPage()->parse('attendance_icon_block', 'attendance_icon');

					if ($this->getGroupHandler()->isStudent($_SESSION['id'])) {
						if ($attendance = $this->getCalendarHandler()->getAttendanceByEventAndUser($event['id'], $_SESSION['id'])) {
							/* Attendance Status */
							if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_PENDING) {
								$this->getPage()->setVar('ATTENDANCE_STATUS', 'Pending');
							}
							else if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_APPROVED) {
								$this->getPage()->setVar('ATTENDANCE_STATUS', 'Approved');
							}
							else if ($attendance['status'] == Calendar::ATTENDANCE_STATUS_REJECTED) {
								$this->getPage()->setVar('ATTENDANCE_STATUS', 'Rejected');
							}

							$this->getPage()->parse('attendance_status_block', 'attendance_status');

							$this->getPage()->setVar('ATTENDANCE_REQUEST_ACTION', 'Unattend');
						}
						else {
							$this->getPage()->setVar('ATTENDANCE_REQUEST_ACTION', 'Attend');
						}

						$this->getPage()->parse('attendance_request_button_block', 'attendance_request_button');
					}
					else if ($this->getGroupHandler()->isStaff($_SESSION['id'])) {
						$this->getPage()->parse('attendance_list_button_block', 'attendance_list_button');
					}
				}

				/* Show a homepage if appropriate
		  		 */
				if (is_numeric($event['categoryId'])) {
					$this->getPage()->setVar(array(
						'HAS_CATEGORY_ID' => $event['categoryId'],
						'HAS_CATEGORY_NAME' => $event['categoryName'],
					));

					$this->getPage()->parse('has_category_block', 'has_category');
				}
				else {
					$this->getPage()->setVar('has_category_block', '');
				}

				$this->getPage()->parse("note_block", "note", true);
			}
		}
		else if (!$this->getSubout()) {
			$this->getPage()->setVar("note_block", "");
		}
	}

	/**
	 * Render calendar
	 */
	public function render() {
		for ($i = 0; $i < 7; $i++) {
			$currentDay = strtotime("+$i day", $this->getStartOfWeek());

			$this->getPage()->setVar('DATE', date("l jS", $currentDay));
			$this->getPage()->setVar('ADD', '/calendar/modify.php?date=' . date("d-m-Y", $currentDay) . '&returnTo=week');

		    // output submission events
		    $currentDay = strtotime("+$i day", $this->getStartOfWeek());

		    //output selected classes
			if (date("d-m-Y", $this->getSelectedDate()) == date("d-m-Y", $currentDay)) {
				$this->getPage()->setVar("MONTABLE_CSS", "calTableSelected");
			}
			else if (date("d-m-Y", $currentDay) == date("d-m-Y")) {
				$this->getPage()->setVar("MONTABLE_CSS", "calTableCurrent");
			}
			else {
				$this->getPage()->setVar("MONTABLE_CSS", "calTable");
			}

			/* Render submission events
			 */
			$this->renderSubmissionEvents($currentDay);
			$this->renderStudentBirthdays($currentDay);
			$this->renderDayEvents($currentDay);

		    $this->getPage()->parse("day_block", "day", true);
		    $this->getPage()->setVar("note_block", "");
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set calendar handler
	 *
	 * @param $calendarHandler
	 */
	private function setCalendarHandler($calendarHandler) {
		$this->calendarHandler = $calendarHandler;
	}

	/**
	 * Get calendar handler
	 *
	 * @return null
	 */
	private function getCalendarHandler() {
		return $this->calendarHandler;
	}

	/**
	 * Set group handler
	 *
	 * @param $groupHandler
	 */
	private function setGroupHandler($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group handler
	 *
	 * @return null
	 */
	private function getGroupHandler() {
		return $this->groupHandler;
	}

	/**
	 * Set sub stu handler
	 *
	 * @param $subStuHandler
	 */
	private function setSubStuHandler($subStuHandler) {
		$this->subStuHandler = $subStuHandler;
	}

	/**
	 * Get sub stu handler
	 *
	 * @return null
	 */
	private function getSubStuHandler() {
		return $this->subStuHandler;
	}

	/**
	 * Set template file
	 *
	 * @param $templateFile
	 */
	private function setTemplateFile($templateFile) {
		$this->templateFile = $templateFile;
	}

	/**
	 * Get template file
	 *
	 * @return string
	 */
	private function getTemplateFile() {
		return $this->templateFile;
	}

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
	 * Set date
	 *
	 * @param $date
	 */
	private function setDate($date) {
		$this->date = $date;
	}

	/**
	 * Get date
	 *
	 * @return string
	 */
	private function getDate() {
		return $this->date;
	}

	/**
	 * Set category list
	 *
	 * @param $categoryList
	 */
	private function setCategoryList($categoryList) {
		$this->categoryList = $categoryList;
	}

	/**
	 * Get category list
	 *
	 * @return array
	 */
	private function getCategoryList() {
		return $this->categoryList;
	}

	/**
	 * Set selected
	 *
	 * @param $selected
	 */
	private function setSelected($selected) {
		$this->selected = $selected;
	}

	/**
	 * Get selected
	 *
	 * @return array
	 */
	private function getSelected() {
		return $this->selected;
	}

	/**
	 * Set selected date
	 *
	 * @param $selectedDate
	 */
	private function setSelectedDate($selectedDate) {
		$this->selectedDate = $selectedDate;
	}

	/**
	 * Get selected date
	 *
	 * @return null
	 */
	private function getSelectedDate() {
		return $this->selectedDate;
	}

	/**
	 * Set start of week
	 *
	 * @param $startOfWeek
	 */
	private function setStartOfWeek($startOfWeek) {
		$this->startOfWeek = $startOfWeek;
	}

	/**
	 * Get start of week
	 *
	 * @return null
	 */
	private function getStartOfWeek() {
		return $this->startOfWeek;
	}

	/**
	 * Set subout
	 *
	 * @param $subout
	 */
	private function setSubout($subout) {
		$this->subout = $subout;
	}

	/**
	 * Get subout
	 *
	 * @return null
	 */
	private function getSubout() {
		return $this->subout;
	}
}
