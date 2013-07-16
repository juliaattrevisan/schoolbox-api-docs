<?php
/**
 * Calendar_Layout_Full_Ajax_JSON
 *
 * This renders the JSON data, but not the page
 * The JSON array contains a set of events that lie
 * within the range specified by the 'start' and 'end'
 * querystring parameters that Fullcalendar uses
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Layout_Full_Ajax_JSON extends Calendar_Layout_Week_Abstract {

	/**
	 * Choose template file
	 */
	protected function setupTemplateFile() {
	}

	/**
	 * Set up blocks and stuff
	 */
	protected function setupBlocks() {
		$this->setupSelectedDayMonthYear();
	}

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

		$this->setStartOfWeek($startOfWeek);
	}

	/**
	 * Render calendar
	 */
	public function render() {
		header('Content-type: application/json');

		$startSequence = new Alaress_Date_Format(Scrub::text($_GET['start']), 'U');
		$endSequence = new Alaress_Date_Format(Scrub::text($_GET['end']), 'U');

		/* Render all registered event types
		 */
		echo "[";

		$renderer = new Calendar_Event_Collection_Renderer($this->getApi(), $startSequence->getUnixTimestamp(), $endSequence->getUnixTimestamp(), $this->getPage());
		$renderer->renderAll();

		/* Final null element to help keep IE happy
		 */
		echo "{}]";

		$page = new SitePage(false);
		$page->saveHistory('/calendar/full/' . $startSequence->getUsingFormat('d-m-Y'));

		exit();
	}
}
