<?php
/**
 * Leave_Core_Report_House_CSV
 *
 * Export method for the House Report
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */


class Leave_Core_Report_House_CSV extends Leave_Report_House_RendererBase {

    /**
     * @var resource $stream
     */
    private $stream;

	protected $decoration = array(
		'pendingStart' => '',
		'pendingEnd' => ' ',

		'approvedStart' => '',
		'approvedEnd' => ' ',
	);

    /**
     * Setup Hook for descendant classes
     * @param array $data extra data for descendant classes
     * @return void
     */
    protected function setup(array $data){
		$this->getPage()->setAjax(true);
	}

	/**
	 * Render it
	 */
	public function render() {
		header("Content-type: text/csv");
		header("Cache-Control: no-store, no-cache");
		header('Content-Disposition: attachment; filename="House Report.csv"');
		$this->setStream(fopen("php://output",'w'));

		$this->renderHeadings();
		$this->renderSubheadings();
		$this->renderRequests();

		fclose($this->getStream());
	}

	/* For each day that the person is on leave within the prescribed period of time, show an
	 * icon. If they are starting or ending their leave on this day, show the time as well
	 *
	 * Render 3 columns each day for 3 days
	 *
	 * Morning: 00:00:00 to 09:59:59
	 * Day: 10:00:00 to 18:59:59
	 * Night: 19:00:00 to 21:59:59
	 */
	protected function renderHeadings() {
		$fieldList = array('Name', 'Year', 'Boarding Status');

		for ($j = 0; $j < $this->getRange(); $j++) {
			$headerDate = new Alaress_Date_Format($this->getReportStart()->getUnixTimestamp(), 'U');
			$headerDate->addDays($j);

			/* Skip AM for Friday if using Weekend view
			 */
			$fieldList[] = $headerDate->getLongDateOnly();
			$fieldList[] = '';

			if ($j || 1 === $this->getRange()) {
				$fieldList[] = '';
			}
		}

		$fieldList[] = 'Authorised By';

		/* Stream it
		 */
		fputcsv($this->getStream(), $fieldList);
	}

	/**
	 * Render subheadings
	 */
	protected function renderSubheadings() {
		$fieldList = array('', '', '');

		/* Skip AM for Friday if not single-day view
		 */
		for ($j = 0; $j < $this->getRange(); $j++) {
			if ($j || 1 === $this->getRange()) {
				$fieldList[] = 'AM';
			}

			$fieldList[] = 'PM';
			$fieldList[] = 'Bed';
		}

		/* Stream it!
		 */
		fputcsv($this->getStream(), $fieldList);
	}

	/**
	 * Render it!
	 */
	protected function renderRequests() {
		if (count($this->getRequestList())) {
			$allPeople = $this->getCombined();

			if (is_array($allPeople) && count($allPeople)) {
				foreach($allPeople as $userId => $currentPerson) {
					$fieldList = array();

					/* Personal details
					 */
					$fieldList[] = $currentPerson['user_name'];
					$fieldList[] = $currentPerson['user_year'];
					$fieldList[] = $currentPerson['user_boarding_status'];

					/* Periods
					 */
					for ($j = 0; $j < count($currentPerson['periods']); $j++) {
						$currentPeriod = $currentPerson['periods'][$j]['request_period'];

						if ($j || 1 === $this->getRange()) {
							$fieldList[] = $currentPeriod['morning'][0]['display'];
						}

						$fieldList[] = $currentPeriod['day'][0]['display'];
						$fieldList[] = $currentPeriod['night'][0]['display'];
					}

					/* Authorisation
					 */
					if (isset($currentPerson['request_authorisation_user_id'])) {
						$fieldList[] = implode(' / ', $currentPerson['request_authorisation_user_id']);
					}
					else {
						$fieldList[] = '';
					}

					/* Stream it
					 */
					fputcsv($this->getStream(), $fieldList);
				}
			}
		}
	}

	/* Getters/Setters
	 */

    /**
     * @param resource $stream
     */
    protected function setStream($stream) {
        $this->stream = $stream;
    }

	/**
	 * Get stream
	 *
	 * @return mixed
	 */
    protected function getStream() {
		return $this->stream;
	}
}
