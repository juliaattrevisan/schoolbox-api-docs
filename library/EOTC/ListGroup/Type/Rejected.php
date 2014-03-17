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


class Rejected extends Base {

	/**
	 * Next status id
	 *
	 * @var int
	 */
	protected $nextStatusId = \EOTC\EventStatusMapper::STATUS_REJECTED;

	/**
	 * Determine where to go next
	 *
	 * @return $this|mixed
	 */
	public function determine() {

		/* All Rejected events end up in their own feedback cycle
		 */
		if (($this->getEvent()->getMetadata()->getStatus()->isRejected())) {
			return new \EOTC\ListGroup\Response($this->getNextStatusId(), $this->getMapping()->getRejectedIndex());
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->determine();
		}
	}
} 