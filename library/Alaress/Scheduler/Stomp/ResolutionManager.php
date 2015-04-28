<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Scheduler\Stomp;


use Alaress\Scheduler\Trigger\TriggerInterface;
use OutOfBoundsException;
use React\Stomp\AckResolver;
use RuntimeException;
use SplObjectStorage;

class ResolutionManager {

	/** @var SplObjectStorage|AckResolver[] */
	protected $resolvers;

    /** @var AckResolver[] */
    protected $resolversIndexedByTriggerHash = array();

	/**
	 * Instantiate members
	 */
	function __construct() {
		$this->resolvers = new SplObjectStorage();
	}


	/**
	 * Attach the given trigger to the given resolver, adding the resolver first if necessary.
	 *
	 * @param AckResolver $resolver
	 * @param TriggerInterface $trigger
	 * @return bool
	 */
	public function attachTriggerToResolver($resolver, $trigger) {
		$data = $this->getData($resolver);
		$data->attach($trigger);
		$this->setData($resolver, $data);
        $this->resolversIndexedByTriggerHash[$trigger->getHash()] = $resolver;
	}


    /**
     * If the final trigger of a resolver is detached, the resolver will be ACK'd and unset
     * Returns the resultant trigger count for the resolver to which it was attached (or -1 otherwise)
     *
     * @param TriggerInterface $trigger
     * @throws \OutOfBoundsException
     * @return int
     */
	public function detachTrigger($trigger) {
		// see if this trigger is referenced
        if (!array_key_exists($trigger->getHash(), $this->resolversIndexedByTriggerHash)) {
            throw new OutOfBoundsException("Given trigger is not attached to any resolver");
        }

        // we know this trigger existed once, but its resolver has already been acknowledged
        if (is_null($this->resolversIndexedByTriggerHash[$trigger->getHash()])) {
            throw new OutOfBoundsException("Resolver for this trigger has already been acknowledged");
        }

        $resolver = $this->resolversIndexedByTriggerHash[$trigger->getHash()];

        /** @var SplObjectStorage|TriggerInterface[] $triggers */
        $triggers = $this->resolvers->offsetGet($resolver);

        if(!$triggers->contains($trigger)) {
            throw new OutOfBoundsException("Given trigger is not attached to the expected resolver");
        }

        $this->resolversIndexedByTriggerHash[$trigger->getHash()] = null;

        $triggers->detach($trigger);
        $this->setData($resolver, $triggers);

        if($this->getTriggerCount($resolver) === 0) {
            // detach this first, so we're not dependent on whether it resolves or not
            $this->resolvers->detach($resolver);

            try {
                $resolver->ack();
            }
            catch(RuntimeException $e) {
                // noop: this message has already been acknowledged, so we can safely drop it
            }
        }

        return $this->getTriggerCount($resolver);
	}

	/**
	 * Gets the data for the given resolver, implicitly attaching it first if necessary
	 *
	 * @param AckResolver $resolver
	 * @return SplObjectStorage|TriggerInterface[]
	 */
	protected function getData($resolver) {
		if(!$this->resolvers->contains($resolver)) {
			$this->resolvers->attach($resolver, new SplObjectStorage());
		}
		return $this->resolvers->offsetGet($resolver);
	}

	/**
	 * @param AckResolver $resolver
	 * @param SplObjectStorage|TriggerInterface[] $triggers
	 */
	protected function setData($resolver, $triggers) {
		$this->resolvers->attach($resolver, $triggers);
	}

	/**
	 * Returns the number of triggers attached to the given resolver. Does NOT implicitly attach.
	 * Will return -1 if the resolver given is NOT attached.
	 *
	 * @param AckResolver $resolver
	 * @return int
	 */
	public function getTriggerCount($resolver) {
		if($this->resolvers->contains($resolver)) {
			return $this->getData($resolver)->count();
		}
		return -1;
	}


    /**
     * @return \React\Stomp\AckResolver[]|SplObjectStorage
     */
    public function getAllResolvers()
    {
        return $this->resolvers;
    }

} 