<?php
/**
 * Chain
 *
 * This figures out which Event List Group and event belongs to and
 * what its next status is
 *
 * This can be used to a) populate the Review screen and b) limit the
 * options available in select dropdowns
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ListGroup;


class Chain {

	/**
	 * Constructor
	 *
	 * @param \EOTC\Event $event
	 */
	public function __construct(\EOTC\Event $event) {

		/* Set up chain of responsibility
		 * Checks occur in this order:
		 *
		 * Urgent,
		 * Pending Final Approval
		 * Confirm RAMs,
		 * Pending In Principle,
		 * Pending Details,
		 * Approved,
		 * Rejected,
		 * None (throws whacky exception)
 		 */
		$pendingFinalApproval = new Type\PendingFinalApproval($event);
		$pendingConfirmRAMs = new Type\PendingConfirmRAMs($event);
		$pendingInPrincipleReview = new Type\PendingInPrincipleReview($event);
		$pendingDetails = new Type\PendingDetails($event);
		$approved = new Type\Approved($event);
		$rejected = new Type\Rejected($event);
		$none = new Type\None($event);

		/* Try until one of them matches
		 */
		$rejected->setNext($none);
		$approved->setNext($rejected);
		$pendingDetails->setNext($approved);
		$pendingInPrincipleReview->setNext($pendingDetails);
		$pendingConfirmRAMs->setNext($pendingInPrincipleReview);
		$pendingFinalApproval->setNext($pendingConfirmRAMs);

		/* Start motor
		 */
		try {
			$this->setResult($pendingFinalApproval->determine());
		}
		catch (\Exception $e) {
			error(163);
		}
	}

	/* Getters/Setters
 	 */

	/**
	 * Set result
	 *
	 * @param string $result
	 */
	private function setResult($result) {
		$this->result = $result;
	}

	/**
	 * @return string
	 */
	public function getResult() {
		return $this->result;
	}
} 