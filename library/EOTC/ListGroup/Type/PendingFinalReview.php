<?php
/**
 * PendingFinalReview
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ListGroup\Type;


class PendingFinalReview extends Base {

	/**
	 * Determine where to go next
	 *
	 * @return $this|mixed
	 */
	public function determine() {

		/* If this requires RAMS and RAMS has been approved,
		 * or if it doesn't require RAMS at all
		 */
		if ($this->getEvent()->getMetadata()->getRamsRequired() && ($this->getEvent()->getMetadata()->getStatus()->getId() == 3)) {
			return new \EOTC\ListGroup\Response(4, $this->getMapping()->getPendingFinalReviewIndex());
		}

		if ($this->getEvent()->getMetadata()->getStatus()->getId() == 2) {
			return new \EOTC\ListGroup\Response(4, $this->getMapping()->getPendingFinalReviewIndex());
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->determine();
		}
	}
} 