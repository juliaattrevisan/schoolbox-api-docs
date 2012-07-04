<?php
/**
 * Calendar_Month_Abstract
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Calendar_Month_Abstract {
	protected $calendarHandler = null;
	protected $groupHandler = null;
	protected $subStuHandler = null;
	protected $page = null;
	protected $date = '';
	protected $categoryList = array();
	protected $templateFile = '';
	protected $selected = array();
	protected $selectedDate = null;

	protected $firstDay = null;
	protected $numDaysInMonth = null;
	protected $totalDays = null;
	protected $startDay = null;

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

	abstract protected function setupSelectedDayMonthYear();

	/**
	 * Setup movement buttons
	 */
	protected function setupMovementButtons() {
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
		$this->getPage()->setVar('BACK', date("j-n-Y", strtotime("-1 month", $this->getSelectedDate())));
		$this->getPage()->setVar('FORWARD', date("j-n-Y", strtotime("+1 month", $this->getSelectedDate())));
	}

	/**
	 * Render student birthdays
	 *
	 * @param $currentDay
	 */
	protected function renderStudentBirthdays($currentDay) {
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
						'EVENT_TYPE' => 'type7', //filter in mgs lookup table
						'TYPE' => 'Birthday',
						'AUTHOR' => '',
						'AUTHOR_ID' => '',
						'EVENT_LEVEL' => '',
					);

					$this->getPage()->setVar($output);
					$this->getPage()->setVar('time_block', '');
					$this->getPage()->parse("note_block", "note", true);
				}
			}
		}
	}

	/**
	 * Render submission events
	 *
	 * @param $currentDay
	 * @return int
	 */
	protected function renderSubmissionEvents($currentDay) {
		$submissionList = $this->getCalendarHandler()->getSubmissionEvents($currentDay);

	    if (is_array($submissionList)) {
	        foreach ($submissionList as $submission) {

	            /* Clear attendance blocks */
	            $this->getPage()->setVar(array(
	                'attendance_icon_block' => '',
	                'attendance_status_block' => '',
	                'attendance_request_button_block' => '',
	                'attendance_list_button_block' => '',
					'has_category_block' => '',
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
	            );

	            $this->getPage()->setVar($output);

				if ($submission['date']->format('g:iA') != '11:59PM') {
					$this->getPage()->parse('time_block', 'time');
				}
				else {
					$this->getPage()->setVar('time_block', '');
				}

				$this->getPage()->parse("note_block", "note", true);
	        }
	    }
	}

	/**
	 * Render day events
	 *
	 * @param $currentDay
	 */
	protected function renderDayEvents($currentDay) {
		$eventList = $this->getCalendarHandler()->getDayEvents($currentDay);

		$iterator = $eventList->createIterator();

		for ($iterator->rewind(); $iterator->valid(); $iterator->next()) {
			$currentEvent = $iterator->current();

			/* Clear attendance blocks
			 */
			$this->getPage()->setVar(array(
				'attendance_icon_block' => '',
				'attendance_status_block' => '',
				'attendance_request_button_block' => '',
				'attendance_list_button_block' => '',
				'has_category_block' => '',
				'time_block' => ''
			));

			/* If the Publish Date hasn't been reached yet, only show the events to Superusers
			 * in a different way so they can still edit/click them. Otherwise, they don't appear
			 * at all for everyone else
			 */
			if ($currentEvent->getPublishDate()->getUnixTimestamp() >= time()) {
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

			/* Render normally if we haven't already skipped it due to being unpublished
			 */
			$this->getPage()->setVar(array(
				'EVENT_ID' => $currentEvent->getEventId(),
				'TIME' => $currentEvent->getDerivedTime(),
				'NOTE' => $currentEvent->getNote(),
				'COLOUR' => $currentEvent->getColour(),
				'LOCATION' => $currentEvent->getLocation(),
				'DETAIL' => $currentEvent->getDetail(),

				/* Wtf - EVENT_TYPE => 'type' . $event['type']; and TYPE => $event['eventType']
				 */
				'EVENT_TYPE' => 'type' . $currentEvent->getType(),
				'TYPE' => $currentEvent->getEventType(),

				'AUTHOR' => $currentEvent->getAuthor(),
				'AUTHOR_ID' => $currentEvent->getAuthorId(),
				'EVENT_VISIBLE' => $cssClass,
				'EVENT_LEVEL' => $currentEvent->getDerivedEventLevel(),
			));

			/* Modify link. Should be templated...
			 */
			$this->getPage()->setVar(array(
				'MODIFY' => $this->getCalendarHandler()->getPerm($currentEvent->getEventId()) > 1 ? '<a class="btn modify float-left" href="/calendar/modify.php?eventID=' . $currentEvent->getEventId() . '&returnTo=month"><span></span>Modify</a>' : '',
			));

			/* If a time was specified, show it; otherwise hide it
			 */
			if (strlen($currentEvent->getDerivedTime())) {
				$this->getPage()->parse('time_block', 'time');
			}

			/* Event attendance
			 */
			if ($currentEvent->canAttend()) {
				$this->getPage()->parse('attendance_icon_block', 'attendance_icon');

				if ($this->getGroupHandler()->isStudent($_SESSION['id'])) {
					if ($attendance = $this->getCalendarHandler()->getAttendanceByEventAndUser($currentEvent->getEventId(), $_SESSION['id'])) {

						/* Attendance Status
						 */
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
			if (is_numeric($currentEvent->getCategoryId())) {
				$this->getPage()->setVar(array(
					'HAS_CATEGORY_ID' => $currentEvent->getCategoryId,
					'HAS_CATEGORY_NAME' => $currentEvent->getCategoryName(),
				));

				$this->getPage()->parse('has_category_block', 'has_category');
			}

			$this->getPage()->parse("note_block", "note", true);
		}
	}

	/* Getters/Setters
	 */

	/**
	 * Set calendar handler
	 *
	 * @param $calendarHandler
	 */
	protected function setCalendarHandler($calendarHandler) {
		$this->calendarHandler = $calendarHandler;
	}

	/**
	 * Get calendar handler
	 *
	 * @return null
	 */
	protected function getCalendarHandler() {
		return $this->calendarHandler;
	}

	/**
	 * Set group handler
	 *
	 * @param $groupHandler
	 */
	protected function setGroupHandler($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * Get group handler
	 *
	 * @return null
	 */
	protected function getGroupHandler() {
		return $this->groupHandler;
	}

	/**
	 * Set sub stu handler
	 *
	 * @param $subStuHandler
	 */
	protected function setSubStuHandler($subStuHandler) {
		$this->subStuHandler = $subStuHandler;
	}

	/**
	 * Get sub stu handler
	 *
	 * @return null
	 */
	protected function getSubStuHandler() {
		return $this->subStuHandler;
	}

	/**
	 * Set template file
	 *
	 * @param $templateFile
	 */
	protected function setTemplateFile($templateFile) {
		$this->templateFile = $templateFile;
	}

	/**
	 * Get template file
	 *
	 * @return string
	 */
	protected function getTemplateFile() {
		return $this->templateFile;
	}

	/**
	 * Set page
	 *
	 * @param $page
	 */
	protected function setPage($page) {
		$this->page = $page;
	}

	/**
	 * Get page
	 *
	 * @return null
	 */
	protected function getPage() {
		return $this->page;
	}

	/**
	 * Set date
	 *
	 * @param $date
	 */
	protected function setDate($date) {
		$this->date = $date;
	}

	/**
	 * Get date
	 *
	 * @return string
	 */
	protected function getDate() {
		return $this->date;
	}

	/**
	 * Set category list
	 *
	 * @param $categoryList
	 */
	protected function setCategoryList($categoryList) {
		$this->categoryList = $categoryList;
	}

	/**
	 * Get category list
	 *
	 * @return array
	 */
	protected function getCategoryList() {
		return $this->categoryList;
	}

	/**
	 * Set selected
	 *
	 * @param $selected
	 */
	protected function setSelected($selected) {
		$this->selected = $selected;
	}

	/**
	 * Get selected
	 *
	 * @return array
	 */
	protected function getSelected() {
		return $this->selected;
	}

	/**
	 * Set selected date
	 *
	 * @param $selectedDate
	 */
	protected function setSelectedDate($selectedDate) {
		$this->selectedDate = $selectedDate;
	}

	/**
	 * Get selected date
	 *
	 * @return null
	 */
	protected function getSelectedDate() {
		return $this->selectedDate;
	}

	/**
	 * Set start of week
	 *
	 * @param $startOfWeek
	 */
	protected function setStartOfWeek($startOfWeek) {
		$this->startOfWeek = $startOfWeek;
	}

	/**
	 * Get start of week
	 *
	 * @return null
	 */
	protected function getStartOfWeek() {
		return $this->startOfWeek;
	}

	/**
	 * Set total days
	 *
	 * @param $totalDays
	 */
	protected function setTotalDays($totalDays) {
		$this->totalDays = $totalDays;
	}

	/**
	 * Get total days
	 *
	 * Get total days
	 *
	 * @return null
	 */
	protected function getTotalDays() {
		return $this->totalDays;
	}

	/**
	 * Set first day
	 *
	 * @param $firstDay
	 */
	protected function setFirstDay($firstDay) {
		$this->firstDay = $firstDay;
	}

	/**
	 * Get first day
	 *
	 * @return null
	 */
	protected function getFirstDay() {
		return $this->firstDay;
	}

	/**
	 * Set num days in month
	 *
	 * @param $numDaysInMonth
	 */
	protected function setNumDaysInMonth($numDaysInMonth) {
		$this->numDaysInMonth = $numDaysInMonth;
	}

	/**
	 * Get num days in month
	 *
	 * @return null
	 */
	protected function getNumDaysInMonth() {
		return $this->numDaysInMonth;
	}

	/**
	 * Set start day
	 *
	 * @param $startDay
	 */
	protected function setStartDay($startDay) {
		$this->startDay = $startDay;
	}

	/**
	 * Get start day
	 *
	 * @return null
	 */
	protected function getStartDay() {
		return $this->startDay;
	}
}
