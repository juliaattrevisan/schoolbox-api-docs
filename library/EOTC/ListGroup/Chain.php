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
 		 */
		$pendingFinalReview = new Type\PendingFinalReview($event);
		$pendingRamsReview = new Type\PendingRamsReview($event);
		$pendingInitialReview = new Type\PendingInitialReview($event);
		$none = new Type\None($event);

		/* Try until one of them matches
		 */
		$pendingInitialReview->setNext($none);
		$pendingRamsReview->setNext($pendingInitialReview);
		$pendingFinalReview->setNext($pendingRamsReview);

		/* Start motor
		 */
		$this->setResult($pendingFinalReview->determine());
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