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


class Approved extends Base {

	/**
	 * Next status id
	 *
	 * @var int
	 */
	protected $nextStatusId = 4;

	/**
	 * Determine where to go next
	 *
	 * @return $this|mixed
	 */
	public function determine() {

		/* If this requires RAMS and RAMS has been approved,
		 * or if it doesn't require RAMS at all
		 */
		if (($this->getEvent()->getMetadata()->getStatus()->isApproved())) {
			return new \EOTC\ListGroup\Response($this->getNextStatusId(), $this->getMapping()->getApprovedIndex());
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->determine();
		}
	}
} 