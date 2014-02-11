<?php
/**
 * Chain
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
		$urgent = new Type\Urgent($event);
		$pendingFinalReview = new Type\PendingFinalReview($event);
		$pendingRamsReview = new Type\PendingRamsReview($event);
		$pendingInitialReview = new Type\PendingInitialReview($event);
		$none = new Type\None($event);

		/* Try until one of them matches
		 */
		$pendingInitialReview->setNext($none);
		$pendingRamsReview->setNext($pendingInitialReview);
		$pendingFinalReview->setNext($pendingRamsReview);
		$urgent->setNext($pendingFinalReview);

		/* Start motor
		 */
		$this->setResult($urgent->determine());
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