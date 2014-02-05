<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message;


use Monolog\Logger;
use Schoolbox\Notif\Notif;
use Schoolbox\User;

class MessageFactory {

	// Use reasonable defaults

	/** @var Notif */
	protected $notif;

	/** @var object */
	protected $object = null;

	/** @var int */
	protected $severity = Logger::INFO;

	/** @var string */
	protected $topic = Notif::TOPIC_SYSTEM;

	/** @var array */
	protected $diff = null;

	/** @var User */
	protected $initiator = null;


	public function __construct($notif) {
		$this->notif = $notif;
	}

	/**
	 * @param object $object
	 * @return self
	 */
	public function setObject($object) {
		$this->object = $object;
		return $this;
	}

	/**
	 * @param int $level
	 * @return self
	 */
	public function setSeverity($level) {
		$this->severity = $level;
		return $this;
	}

	/**
	 * @param string|array $topic
	 * @return self
	 */
	public function setTopic($topic) {
		if(is_string($topic)) {
			$topic = array($topic);
		}
		$this->topic = $topic;
		return $this;
	}

	/**
	 * @param array $diff
	 * @return self
	 */
	public function setDiff($diff) {
		$this->diff = $diff;
		return $this;
	}

	/**
	 * @param User $user
	 * @return self
	 */
	public function setInitiator($user) {
		$this->initiator = $user;
		return $this;
	}

	/**
	 * @param string $method
	 * @return Internal
	 */
	public function generateInternalMessage($method) {
		return new Internal(
			// the what
			$this->object,
			$this->diff,
			// the who
			$this->initiator,
			// the how
			$method,
			// delivery details
			$this->severity,
			$this->topic,
			$this->notif
		);
	}

	/**
	 * @return External
	 */
	public function generateExternalMessage() {

	}

	/**
	 * Reconstitute a message that has come through in binary format
	 *
	 * @param $encodedCompressedSerialized
	 * @throws CompressionException
	 * @return MessageInterface
	 */
	public function reconstitute($encodedCompressedSerialized) {

		$compressedSerialized = base64_decode($encodedCompressedSerialized);
		$serialized = gzuncompress($compressedSerialized);
		if($serialized === false) {
			throw new CompressionException("Could not decompress message data");
		}
		$data = unserialize($serialized);

		$message = unserialize($data['message']);
		return $message;
	}

}