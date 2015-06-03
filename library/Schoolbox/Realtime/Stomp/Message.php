<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Stomp;


use React\Stomp\AckResolver;
use React\Stomp\Protocol\Frame;

class Message {

	/** @var Frame */
	public $frame;

	/** @var AckResolver */
	protected $ackResolver;

	/**
	 * @param Frame $frame
	 * @param AckResolver $ackResolver
	 */
	public function __construct($frame, $ackResolver) {
		$this->frame = $frame;
		$this->ackResolver = $ackResolver;
	}

	/**
	 * Passthru to ackresolver->ack()
	 *
	 * @param array $headers
	 */
	public function ack($headers = array()) {
		$this->ackResolver->ack($headers);
	}

	/**
	 * Passthru to ackresolver->nack()
	 *
	 * @param array $headers
	 */
	public function nack($headers = array()) {
		$this->ackResolver->nack($headers);
	}

    /**
     * @return string
     */
    public function getAmqpId()
    {
        return $this->frame->getHeader('amqp-message-id');
    }


} 