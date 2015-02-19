<?php
/**
 * Leave_Kings_Report_House_Print
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Leave_Kings_Report_House_Print extends Leave_Kings_Report_House_Web {
	protected $templateFile = '../kings/modules/leave/report/house/print.html';

	/**
	 * Render extra information about the report
	 *
	 * Augment functionality (even though call super is bad)
	 */
	protected function renderMetaDetails() {
		parent::renderMetaDetails();

		$weekEnding = new Alaress_Date_Format($this->getReportDate(), 'd/m/Y');
		$weekEnding->addDays(Leave_Controller::SUNDAY - $weekEnding->getWeekDayNumber());

		$this->getPage()->setVar(array(
			'WEEK_ENDING' => $weekEnding->getUsingFormat('l F j, Y'),
		));
	}
}
