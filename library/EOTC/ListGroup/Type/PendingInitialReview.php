<?php
/**
 * PendingInitialReview
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ListGroup\Type;


class PendingInitialReview extends Base {

	/**
	 * Determine where to go next
	 *
	 * @return $this|mixed
	 */
	public function determine() {

		/* If this requires RAMS and RAMS has been approved,
		 * or if it doesn't require RAMS at all
		 */
		if (($this->getEvent()->getMetadata()->getStatus()->getId() == 1)) {
			return array(
				'nextStatus' => 2,
				'listGroupIndex' => $this->getMapping()->getPendingInitialReviewIndex()
			);
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->determine();
		}
	}
} 