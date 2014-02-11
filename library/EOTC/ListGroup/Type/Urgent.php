<?php
/**
 * Urgent
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ListGroup\Type;


class Urgent extends Base {

	/**
	 * Determine where to go next
	 *
	 * @return $this|mixed
	 */
	public function determine() {
//		if ($this->getEvent()->getMetadata()->getStatus()->getId() == 1) {
//			return array(
//				'nextStatus' => 2,
//				'listGroupIndex' => 0
//			);
//		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->determine();
		}
	}
} 