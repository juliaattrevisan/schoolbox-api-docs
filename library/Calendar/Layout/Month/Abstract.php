<?php
/**
 * Calendar_Layout_Month_Abstract
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Calendar_Layout_Month_Abstract {
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

	/* Actual connector instance
	 */
	protected $api = null;

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $user
	 * @param $date
	 */
	public function __construct($page, $user, $date) {
		$factory = new Calendar_Api_Factory($user);
		$api = $factory->produce();

		$this->setApi($api);

		/* Still used for perms and stuff
		 */
		$this->setCalendarHandler(new Calendar());

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

	/**
	 * Set api
	 *
	 * @param $api
	 */
	protected function setApi($api) {
		$this->api = $api;
	}

	/**
	 * Get api
	 *
	 * @return null
	 */
	protected function getApi() {
		return $this->api;
	}
}
