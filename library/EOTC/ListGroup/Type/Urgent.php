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
	 * N days before the event starts causes an event to be
	 * put into Urgent
	 *
	 * @var int
	 */
	private $threshold = 3;

	/**
	 * Determine where to go next
	 *
	 * @return $this|mixed
	 */
	public function determine() {
		$difference = $this->getEvent()->getStartDateTime()->getUnixTimestamp() - $_SERVER['REQUEST_TIME'];

		/* Find out what the event's next status should be
		 */
		$chain = new \EOTC\ListGroup\Chain($this->getEvent());
		$location = $chain->getResult();

		/* 3 days before start of event: throw it into the Urgent category
		 * regardless of what its current status is (unless it's already
		 * Approved/Rejected, at which point no further action is necessary)
		 */
		$isApproved = $this->getEvent()->getMetadata()->getStatus()->isApproved();
		$isRejected = $this->getEvent()->getMetadata()->getStatus()->isRejected();

		if ($this->isWithinThreshold($difference) && !$isApproved && !$isRejected) {
			return new \EOTC\ListGroup\Response($location->getNextStatus(), $this->getMapping()->getUrgentIndex());
		}

		/* Not urgent; proceed as normal
		 */
		return $location;
	}

	/**
	 * Determine whether you're within a certain threshold of
	 * a particular timestamp
	 *
	 * @param $timeDifference
	 * @return bool
	 */
	private function isWithinThreshold($timeDifference) {
		if ($timeDifference < 60 * 60 * 24 * $this->getThreshold()) {
			return true;
		}

		return false;
	}

	/* Getters/Setters
	 */

	/**
	 * Set threshold
	 *
	 * @param int $threshold
	 */
	private function setThreshold($threshold) {
		$this->threshold = $threshold;
	}

	/**
	 * Get threshold
	 *
	 * @return int
	 */
	private function getThreshold() {
		return $this->threshold;
	}
}