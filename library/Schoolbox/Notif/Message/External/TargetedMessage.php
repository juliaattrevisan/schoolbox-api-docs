<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Message\External;


use Alaress\Scheduler\Trigger\TriggerInterface;
use InvalidArgumentException;
use Schoolbox\Notif\Message\Instance\Instance;
use Schoolbox\Notif\Message\Message;
use Schoolbox\Notif\Notif;
use Schoolbox\Realtime\Message\NS;
use Schoolbox\Realtime\Schedule\Trigger\InstantTrigger;
use Serializable;

/**
 * Class TargetedMessage
 *
 * @deprecated
 * @package Schoolbox\Notif\Message\External
 */
class TargetedMessage implements Serializable {

    const MATCH_PARAMS     = 0;
    const MATCH_COMPELTELY = 1;

    /** @var Message  */
    protected $body;
    /** @var int */
    protected $timestamp;
    /** @var Notif */
    protected $notif;
    /** @var string */
    protected $topicPrepend = 'notify.';
    /** @var Instance */
    protected $instance;
	/** @var string */
	protected $namespace = 'activity';

	/**
	 * @deprecated
	 * @param Notif $notif
	 * @param Message $body
	 * @param Instance $instance
	 * @param TriggerInterface $trigger
	 * @throws InvalidArgumentException
	 */
    public function __construct($notif, $body, $instance, $trigger = null) {
        $this->body = $body;
        $this->notif = $notif;
        $this->instance = $instance;

	    if($trigger) {
		    if($trigger instanceof TriggerInterface) {
			    $this->timestamp = $trigger->getTimestamp();
			    if($trigger instanceof InstantTrigger) {
				    $ns = $trigger->getModuleNotifier()->getNamespace();
				    if($ns instanceof NS) {
					    $this->namespace = $trigger->getModuleNotifier()->getNamespace()->getSlug();
				    }
				    else {
					    throw new InvalidArgumentException("Namespace undefined for message");
				    }
			    }
		    }
	    }
    }


	/**
	 * @return \Schoolbox\Notif\Message\Message
	 */
	public function getBody() {
		return $this->body;
	}

	/**
	 * @return \Schoolbox\Notif\Message\Instance\Instance
	 */
	public function getInstance() {
		return $this->instance;
	}


	/**
	 * @return int|null
	 */
	public function getTimestamp() {
        return $this->timestamp;
    }

	/**
	 * Set the targets of this message.
	 *
	 * @param $array
     * @return self
	 */
	public function setTargets($array) {
		$this->instance->setTargets($array);
        return $this;
	}


    /**
     * @return array
     */
    public function getTargets() {
		return $this->instance->getTargets();
	}


    /**
     * @return array
     */
    public function getParams() {
        return $this->instance->getParams();
    }


	/**
	 * @return string
	 */
	public function getNamespace() {
		return $this->namespace;
	}

	/**
	 * @return bool
	 */
	public function send() {
		// cannot send if there is no notifier to pass through
		if(is_null($this->notif)) {
			return false;
		}

		foreach($this->instance->getTargets() as $target) {
			foreach($this->instance->getTopics() as $topic) {
				$self = serialize($this);
				$topicString = sprintf('%s%s.%s', $this->topicPrepend, $target, $topic);
				$this->notif
                    ->setTopic($topicString)
                    ->log($this->instance->getSeverity(), $self, $this->instance->getContext());
			}
		}

		return true;
	}

	/**
	 * @param Notif $notif
	 * @param int $severity
	 * @param string|array $topic
	 * @param string $topicPrepend
	 * @return bool
	 */
	public function resend($notif, $severity, $topic, $topicPrepend = '') {
		$this->notif = $notif;
		$this->topicPrepend = $topicPrepend;
		$this->instance->setSeverity($severity)->setTopics((array)$topic);
		return $this->send();
	}


    /**
     * @param array $context
     * @return self
     */
    public function setContext($context = array()) {
        $this->instance->setContext($context);
        return $this;
    }


    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * String representation of object
     * @link http://php.net/manual/en/serializable.serialize.php
     * @return string the string representation of the object or null
     */
    public function serialize() {
        return serialize(array($this->body, $this->timestamp, $this->instance));
    }


    /**
     * (PHP 5 &gt;= 5.1.0)<br/>
     * Constructs the object
     * @link http://php.net/manual/en/serializable.unserialize.php
     * @param string $serialized <p>
     * The string representation of the object.
     * </p>
     * @return void
     */
    public function unserialize($serialized) {
        list($this->body, $this->timestamp, $this->instance) = unserialize($serialized);
        $this->notif = null;
    }


    /**
     * Returns whether or not the given message matches this one, to varying strictness of comparison
     *
     * @param self $compare
     * @param int $strength
     * @return bool
     */
    public function matches($compare, $strength = self::MATCH_PARAMS) {
        if(!$compare instanceof self) {
            return false;
        }
        switch($strength) {
            case self::MATCH_PARAMS:
                return $compare->getParams() == $this->getParams();
            case self::MATCH_COMPELTELY:
                return $compare == $this;
        }
        return false;
    }

}
