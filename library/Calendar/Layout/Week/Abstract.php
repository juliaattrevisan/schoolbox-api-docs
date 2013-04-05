<?php
/**
 * Calendar_Layout_Week_Abstract
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Calendar_Layout_Week_Abstract {
	protected $calendarHandler = null;
	protected $groupHandler = null;
	protected $subStuHandler = null;
	protected $page = null;
	protected $date = '';
	protected $categoryList = array();
	protected $templateFile = '';
	protected $selected = array();
	protected $selectedDate = null;
	protected $startOfWeek = null;

	/* Actual connector instance
	 */
	protected $api = null;

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $date
	 */
	public function __construct($page, $date) {
		$factory = new Calendar_Api_Factory();
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

	abstract protected function setupTemplateFile();
	abstract protected function setupBlocks();
	abstract protected function render();

	/**
	 * Set up selected day/month/year
	 */
	protected function setupSelectedDayMonthYear() {
		list($selectedDay, $selectedMonth, $selectedYear) = explode("-", $this->getDate());

		$selectedDate = @mktime(0, 0, 0, $selectedMonth, $selectedDay, $selectedYear);

		/* Default url access doesn't have a date portion
		 * i.e. /calendar/week/. In that case, just use now
		 */
		if ($selectedDate <= 0) {
		    $selectedDate = time();
		}

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

		$this->getPage()->checkList('filter_group', $this->getCategoryList(), $selected['group'], TEMPLATE_content, array('name' => 'GROUP_NAME', 'id' => 'GROUP_ID'));
		$this->getPage()->checkList('filter_campus', $this->getCalendarHandler()->getFilterCampusList($_SESSION['id']), $selected['campus']);
		$this->getPage()->checkList('filter_event_type', $this->getCalendarHandler()->getTypeList(), $selected['event_type'], TEMPLATE_content, array('eventType' => 'EVENT_TYPE_NAME', 'eventTypeID' => 'EVENT_TYPE_ID', 'eventColour' => 'EVENT_TYPE_COLOUR'));
		$this->getPage()->setVar('BACK', date("j-n-Y", strtotime("-1 week", $this->getSelectedDate())));
		$this->getPage()->setVar('FORWARD', date("j-n-Y", strtotime("+1 week", $this->getSelectedDate())));
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
	 * @return SitePage
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
