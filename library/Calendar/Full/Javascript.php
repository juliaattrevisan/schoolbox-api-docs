<?php
/**
 * Calendar_Full_Javascript
 *
 * This renders the page in which the calendar will live,
 * but not the data. This separates responsibilities even
 * though it seems like excessive amount of class usage.
 *
 * The javascript calendar is all self contained.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Full_Javascript extends Calendar_Week_Abstract {

	/**
	 * Choose template file
	 */
	protected function setupTemplateFile() {
		$this->setTemplateFile('calendar/javascript/week.html');
	}

	/**
	 * Set up blocks and stuff
	 */
	protected function setupBlocks() {
		$this->getPage()->setFile(TEMPLATE_content, $this->getTemplateFile());
	}

	/**
	 * Set up selected day/month/year
	 */
	protected function setupSelectedDayMonthYear() {
	}

	/**
	 * Render calendar
	 */
	public function render() {
	}
}
