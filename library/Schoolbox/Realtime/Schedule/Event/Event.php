<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Schoolbox\Realtime\Schedule\Event;


use JsonSerializable;
use RuntimeException;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Serializable;

class Event implements CrudEventInterface, Serializable, JsonSerializable {

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
     * @throws Mapper\Exception
     * @return string the string representation of the object or null
     */
	public function serialize() {
		return serialize($this->jsonSerialize());
	}


    /**
     * Constructs the object
     *
     * @param string $serialized The string representation of the object.
     * @throws Mapper\Exception
     * @throws RuntimeException
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

        // The hydrate cache must be invalidated for this object,
        // because this object could have already been hydrated in this request.
        $this->mapper->invalidateHydrateCache($array['o']['i']);

        $this->object = $this->hydrate($array['o']['i'], $this->mapper);

		$this->diff     = $array['d'];
	}


    /**
     * Attempt to convert the serialised array into a model object.
     *
     * If the class structure/signature has changed during serialisation, overlay serialised data over
     * current model if possible and model is white-listed
     *
     * @param mixed[] $instanceData
     * @param Mapper  $mapper
     * @return mixed
     * @throws Mapper\Exception
     * @throws Mapper\Exception\Deleted
     * @throws \TypeError
     */
    private function hydrate(array $instanceData, Mapper $mapper)
    {
        try {
            return $mapper->hydrate($instanceData);
        } catch (\TypeError $e) {
            // It's possible the model's class has changed since the object was serialised, and the serialised data
            // is no longer able to hydrate the model correctly. In this case, fall back to the current version of this model
            // stored in the DB, and overlay as much as we can.
            $identifier = $mapper->getIdentifier();
            if (!$identifier) {
                // No Identifier? Not much we can do.
                throw $e;
            }
            // White list of models that are known to have this issue, and which we are confident this implementation will work.
            $knownIssueClasses = [SubmissionBox::class,];
            if (!\in_array($mapper->getModel(), $knownIssueClasses, true)) {
                throw $e;
            }
            // Convert from indexed-by-property to indexed-by-column
            $mapsByColumn = $mapper->getMap()
                                   ->filter(function (array $map) {
                                       // Reverse joins don't have a column
                                       return array_key_exists('column', $map);
                                   })
                                   ->indexByCallback(function (array $map) {
                                       return $map['column'];
                                   });
            // Get object as it exists currently in DB
            $object = $mapper->findById($instanceData[$mapper->getIdentifierColumn()]);
            // Overlay serialized values
            foreach ($instanceData as $column => $value) {
                // Check the serialised column still exists on the model (eg submission_box.deleted no longer exists in 18.0)
                if($mapsByColumn->keyExists($column)){
                    $mapper->setModelProperty($object, $mapsByColumn[$column]['property'], $value);
                }

            }
            return $object;
        }
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @throws Mapper\Exception
     * @return array
     */
    function jsonSerialize()
    {
        return [
            'u' => UserMapper::getInstance()->dehydrate($this->effector),
            'a' => $this->getAction(),
            'o' => [
                'c' => get_class($this->getMapper()),
                'i' => $this->getMapper()->dehydrate($this->getObject())
            ],
            'd' => $this->getDiff(),
        ];
    }
}
