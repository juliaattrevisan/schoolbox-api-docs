<?php
/**
 * PendingFinalApproval
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ListGroup\Type;


class AwaitingFinalApproval extends Base {

	/**
	 * Next status id
	 *
	 * @var int
	 */
	protected $nextStatusId = \EOTC\EventStatusMapper::STATUS_APPROVED;

	/**
	 * Determine where to go next
	 *
	 * @return $this|mixed
	 */
	public function determine() {

		/* If it's Awaiting Final Approval, next status is Approved
		 */
		if ($this->getEvent()->getMetadata()->getStatus()->isAwaitingFinalApproval()) {
			return new \EOTC\ListGroup\Response($this->getNextStatusId(), $this->getMapping()->getAwaitingFinalApprovalReviewIndex());
		}

		/* Otherwise get the next class to deal with it
		 */
		if ($this->getNext()) {
			return $this->getNext()->determine();
		}
	}
} 