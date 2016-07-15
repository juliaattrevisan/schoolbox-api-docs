<?php
/**
 * Leave_Kings_Report_House_Print
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */


class Leave_Kings_Report_House_Print extends Leave_Core_Report_House_Print {
	protected $templateFile = '../kings/modules/leave/report/house/print.html';

    /**
     * Offsets from the start time of a day as shown on the report.
     *
     * @var array|int[]
     */
    protected $offsets = [
        'morningStart' => 7,
        'dayStart'     => 11,
        'nightStart'   => 21,
        'nightEnd'     => 31,
    ];

	/**
	 * Render extra information about the report
	 *
	 * Augment functionality (even though call super is bad)
	 */
	protected function renderMetaDetails() {
		parent::renderMetaDetails();

		$weekEnding = Alaress_Date_Format::newLocalShort($this->getReportDate());
		$weekEnding->addDays(Leave_Controller::SUNDAY - $weekEnding->getWeekDayNumber());

		$this->getPage()->setVar(array(
			'WEEK_ENDING' => $weekEnding->getLongDateOnly(),
		));
	}
}
