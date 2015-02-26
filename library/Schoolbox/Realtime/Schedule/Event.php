<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Schoolbox\Realtime\Schedule;


use Alaress\Scheduler\Event\EventInterface;
use RuntimeException;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Serializable;

class Event implements EventInterface, Serializable {

    /** @var User */
    protected $effector;

    /** @var string */
    protected $action;

    /** @var object */
    protected $object;

	/** @var Mapper */
	protected $mapper;

    /** @var array|null */
    protected $diff;


    /**
     * @param User $subject
     * @param string $verb
     * @param object $object
     * @param Mapper $mapper
     * @param array $diff
     */
    function __construct($subject, $verb, $object, $mapper, $diff = null) {
        $this->effector = $subject;
        $this->action   = $verb;
        $this->object   = $object;
	    $this->mapper   = $mapper;
        $this->diff     = $diff;
    }


    /**
     * Returns the principal that caused this event
     *
     * @return User
     */
    public function getEffector() {
        return $this->effector;
    }


    /**
     * Returns the name of the action that was taken by the effector against the object
     *
     * @return string
     */
    public function getAction() {
        return $this->action;
    }


    /**
     * Returns the object that is being acted upon
     *
     * @return object
     */
    public function getObject() {
        return $this->object;
    }


	/**
     * @param object $object
     * @return self
     */
    public function setObject($object)
    {
        $this->object = $object;
        return $this;
    }


    /**
	 * @return Mapper
	 */
	public function getMapper() {
		return $this->mapper;
	}


    /**
     * Returns an array that represents the difference between the object in its current state
     * and its previous state (empty array means "no change"), or null if not applicable for the given action.
     *
     * @return array|null
     */
    public function getDiff() {
        return $this->diff;
    }


	/**
	 * String representation of object
	 *
	 * @return string the string representation of the object or null
	 */
	public function serialize() {
		return serialize(array(
			'u' => UserMapper::getInstance()->dehydrate($this->effector),
			'a' => $this->getAction(),
			'o' => array(
				'c' => get_class($this->getMapper()),
				'i' => $this->getMapper()->dehydrate($this->getObject())
			),
			'd' => $this->getDiff(),
		));
	}


	/**
	 * Constructs the object
	 *
	 * @param string $serialized The string representation of the object.
	 * @return void
	 */
	public function unserialize($serialized) {
		$array = unserialize($serialized);

        // urgh. more safety checks
        if (!class_exists($array['o']['c'])) {
            throw new RuntimeException("Could not unserialize non-existent mapper");
        }

		$this->effector = UserMapper::getInstance()->hydrate($array['u']);
		$this->action   = $array['a'];
		$this->mapper   = $array['o']['c']::getInstance();
		$this->object   = $this->getMapper()->hydrate($array['o']['i']);
		$this->diff     = $array['d'];
	}
}