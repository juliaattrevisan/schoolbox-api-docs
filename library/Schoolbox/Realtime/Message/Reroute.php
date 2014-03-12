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
	/** @var string */
	protected $relationType;
	/** @var string */
	protected $routingKey;
	/** @var int */
	protected $incomingLevel;
	/** @var int */
	protected $outgoingLevel;

	/**
	 * @param int $incomingLevel
	 */
	public function setIncomingLevel($incomingLevel) {
		$this->incomingLevel = $incomingLevel;
	}

	/**
	 * @return int
	 */
	public function getIncomingLevel() {
		return $this->incomingLevel;
	}

	/**
	 * @param int $outgoingLevel
	 */
	public function setOutgoingLevel($outgoingLevel) {
		$this->outgoingLevel = $outgoingLevel;
	}

	/**
	 * @return int
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
	 * @param string $relationType
	 */
	public function setRelationType($relationType) {
		$this->relationType = $relationType;
	}

	/**
	 * @return string
	 */
	public function getRelationType() {
		return $this->relationType;
	}

	/**
	 * @param string $routingKey
	 */
	public function setRoutingKey($routingKey) {
		$this->routingKey = $routingKey;
	}

	/**
	 * @return string
	 */
	public function getRoutingKey() {
		return $this->routingKey;
	}




} 