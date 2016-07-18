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
     * Facade method
     * Render house report
     */
    public function render()
    {
        $this->renderMetaDetails();
        $this->renderTableHeaders();
        $this->renderTableRowsAndFooter();
    }


	/**
	 * Render extra information about the report
	 *
	 * Augment functionality (even though call super is bad)
	 */
	protected function renderMetaDetails() {
		parent::renderMetaDetails();

        $this->getPage()->setBlock(TEMPLATE_content, 'heading_single_day', 'heading_single_day_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'heading_weekend',    'heading_weekend_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'heading_date_range', 'heading_date_range_block');

        $this->getPage()->setVar([
            'heading_single_day_block' => '',
            'heading_weekend_block' => '',
            'heading_date_range_block' => '',
        ]);

        switch($this->getReportTimespan()){
            case Leave_Controller::REPORT_TIMESPAN_DAY:
                $this->getPage()->setVar('SINGLE_DAY_DATE', $this->getReportStart()->getLongDateOnly());
                $this->getPage()->parse('heading_single_day_block','heading_single_day');
                break;
            case Leave_Controller::REPORT_TIMESPAN_RANGE:
                $this->getPage()->setVar([
                    'RANGE_START' => $this->getReportStart()->getDateOnly(),
                    'RANGE_END'   => $this->getReportFinish()->getDateOnly()
                ]);
                $this->getPage()->parse('heading_date_range_block','heading_date_range');
                break;
            case Leave_Controller::REPORT_TIMESPAN_WEEKEND:
            default:
                $weekEnding = Alaress_Date_Format::newLocalShort($this->getReportStartDate());
                $weekEnding->addDays(Leave_Controller::SUNDAY - $weekEnding->getWeekDayNumber());
                $this->getPage()->setVar('WEEK_ENDING', $weekEnding->getLongDateOnly());
                $this->getPage()->parse('heading_weekend_block','heading_weekend');
                break;
        }



	}
}
