<?php
/**
 * PendingDetails
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ListGroup\Type;

use \EOTC\EventStatusMapper;
use \EOTC\ListGroup\Response;

class PendingDetails extends Base {

	/**
	 * Next status id
	 *
	 * @var int
	 */
	protected $nextStatusId = EventStatusMapper::STATUS_DETAILSCOMPLETED;

	/**
	 * Determine where to go next
	 *
	 * @return $this|mixed
	 */
	public function determine() {

		/* This comprises events which came from In Principle
		 * with the RAMS Required configuration
		 */
		if ($this->getEvent()->getMetadata()->getRamsRequired() && ($this->getEvent()->getMetadata()->getStatus()->isInPrinciple())) {
			return new Response($this->getNextStatusId(), $this->getMapping()->getPendingDetailsIndex());
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->determine();
		}
	}
}