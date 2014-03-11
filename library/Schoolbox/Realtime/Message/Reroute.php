<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Message;


class Reroute {

	/** @var int */
	protected $relationId;
	/** @var int */
	protected $relationType;
	/** @var int */
	protected $routingKey;
	/** @var string */
	protected $incomingLevel;
	/** @var string */
	protected $outgoingLevel;

	/**
	 * @param string $incomingLevel
	 */
	public function setIncomingLevel($incomingLevel) {
		$this->incomingLevel = $incomingLevel;
	}

	/**
	 * @return string
	 */
	public function getIncomingLevel() {
		return $this->incomingLevel;
	}

	/**
	 * @param string $outgoingLevel
	 */
	public function setOutgoingLevel($outgoingLevel) {
		$this->outgoingLevel = $outgoingLevel;
	}

	/**
	 * @return string
	 */
	public function getOutgoingLevel() {
		return $this->outgoingLevel;
	}

	/**
	 * @param int $relationId
	 */
	public function setRelationId($relationId) {
		$this->relationId = $relationId;
	}

	/**
	 * @return int
	 */
	public function getRelationId() {
		return $this->relationId;
	}

	/**
	 * @param int $relationType
	 */
	public function setRelationType($relationType) {
		$this->relationType = $relationType;
	}

	/**
	 * @return int
	 */
	public function getRelationType() {
		return $this->relationType;
	}

	/**
	 * @param int $routingKey
	 */
	public function setRoutingKey($routingKey) {
		$this->routingKey = $routingKey;
	}

	/**
	 * @return int
	 */
	public function getRoutingKey() {
		return $this->routingKey;
	}



} 