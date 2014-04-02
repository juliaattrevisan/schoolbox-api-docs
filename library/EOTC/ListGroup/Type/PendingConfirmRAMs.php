<?php
/**
 * AwaitingFinalApproval
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ListGroup\Type;


class PendingConfirmRAMs extends Base {

	/**
	 * Next status id
	 *
	 * @var int
	 */
	protected $nextStatusId = \EOTC\EventStatusMapper::STATUS_AWAITINGFINALAPPROVAL;

	/**
	 * Determine where to go next
	 *
	 * @return $this|mixed
	 */
	public function determine() {

		/* This comprises events which came from:
		 *
		 * In Principle (RAMS Not Required) and
		 * RAMS Approved (RAMS Required)
		 */
		if (!$this->getEvent()->getMetadata()->getRamsRequired() && $this->getEvent()->getMetadata()->getStatus()->isInPrinciple()) {
			return new \EOTC\ListGroup\Response($this->getNextStatusId(), $this->getMapping()->getPendingConfirmRAMsReviewIndex());
		}
		else if ($this->getEvent()->getMetadata()->getRamsRequired() && $this->getEvent()->getMetadata()->getStatus()->isRamsApproved()) {
			return new \EOTC\ListGroup\Response($this->getNextStatusId(), $this->getMapping()->getPendingConfirmRAMsReviewIndex());
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->determine();
		}
	}
} 