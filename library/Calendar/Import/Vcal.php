<?php
/**
 * Vcal
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Calendar\Import;

use Sabre\VObject;

class Vcal extends Base {

	/**
	 * Load one event into schoolbox, generateo outcome array
	 */
	public function load() {
		if (is_file($this->getFile())) {
			$allEvents = VObject\Reader::read(file_get_contents($this->getFile()));

			foreach ($allEvents as $currentEvent) {
				new \Alaress_Probe($currentEvent);
			}
         }
	}

//{
//		if ($this->getCalendarHandler()->create($event)) {
//			$output[] = array(
//				'name' => $summary,
//				'status' => 'Added',
//				'error' => 'OK',
//			);
//		}
//		else {
//			$output[] = array(
//				'name' => $summary,
//				'status' => 'Not Added',
//				'error' => 'Could not create event',
//			);
//		}
//	}
}