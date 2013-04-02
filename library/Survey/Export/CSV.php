<?php
/**
 * Survey_Export_CSV
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Survey_Export_CSV extends Evaluation_Export_CSV {
	protected $additionalColumns = array();

	/**
	 * Hook method
	 *
	 * @return mixed|void
	 */
	protected function setup() {
	}

	/**
	 * Filter out anonymous results
	 *
	 * Normally should be done in a more meaningful way at collation
	 * but because it's shared with assess, we don't.
	 *
	 * @param $survey
	 */
	public function adjustAnonymousResult($survey) {
		if ($survey['anonymous_result']) {
			$this->adjustHeadings();
			$this->adjustResponses();
		}
	}

	/**
	 * Adjust headings for Anonymity
	 */
	private function adjustHeadings() {
		$allHeadings = $this->getHeadings();

		/* Adjust headings
		 */
		array_shift($allHeadings);
		array_shift($allHeadings);

		$this->setHeadings($allHeadings);
	}

	/**
	 * Adjust responses for Anonymity
	 */
	private function adjustResponses() {
		$allResponses = $this->getResponses();

		$collection = array();

		/* Adjust responses
		 */
		foreach($allResponses as $currentResponse) {
			array_shift($currentResponse);
			array_shift($currentResponse);

			$collection[] = $currentResponse;
		}

		$this->setResponses($collection);
	}
}
