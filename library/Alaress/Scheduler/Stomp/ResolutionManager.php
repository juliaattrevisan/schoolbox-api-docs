<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Scheduler\Stomp;


use Alaress\Async\Broker\IResolver;
use Alaress\Scheduler\Trigger\TriggerInterface;
use OutOfBoundsException;
use RuntimeException;
use SplObjectStorage;

class ResolutionManager
{

	/** @var SplObjectStorage|IResolver[] */
	protected $resolvers;

    /** @var IResolver[] */
    protected $resolversIndexedByTriggerHash = [];

	/**
	 * Instantiate members
	 */
	public function __construct()
    {
		$this->resolvers = new SplObjectStorage();
	}


	/**
	 * Attach the given trigger to the given resolver, adding the resolver first if necessary.
	 *
	 * @param IResolver $resolver
	 * @param TriggerInterface $trigger
	 * @return void
	 */
	public function attachTriggerToResolver($resolver, $trigger)
    {
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
     * @throws OutOfBoundsException
     * @return int
     */
    public function detachTrigger($trigger)
    {
        $resolver = $this->getResolverByTrigger($trigger);
        $triggers = $this->getTriggersByResolver($resolver);

        if (!$triggers->contains($trigger)) {
            throw new OutOfBoundsException('Given trigger is not attached to the expected resolver');
        }

        $this->resolversIndexedByTriggerHash[$trigger->getHash()] = null;

        $triggers->detach($trigger);
        $this->setData($resolver, $triggers);

        if ($this->getTriggerCount($resolver) === 0) {
            // detach this first, so we're not dependent on whether it resolves or not
            $this->resolvers->detach($resolver);

            try {
                $resolver->acknowledge();
            } catch (RuntimeException $e) {
                // noop: this message has already been acknowledged, so we can safely drop it
            }
        }

        return $this->getTriggerCount($resolver);
    }


    /**
     * @param IResolver $resolver
     * @return SplObjectStorage|TriggerInterface[]
     */
    private function getTriggersByResolver(IResolver $resolver)
    {
        return $this->resolvers->offsetGet($resolver);
    }

	/**
	 * Gets the data for the given resolver, implicitly attaching it first if necessary
	 *
	 * @param IResolver $resolver
	 * @return SplObjectStorage|TriggerInterface[]
	 */
	protected function getData($resolver)
    {
		if (!$this->resolvers->contains($resolver)) {
			$this->resolvers->attach($resolver, new SplObjectStorage());
		}

		return $this->resolvers->offsetGet($resolver);
	}

	/**
	 * @param IResolver $resolver
	 * @param SplObjectStorage|TriggerInterface[] $triggers
     * @return void
	 */
	protected function setData($resolver, $triggers)
    {
		$this->resolvers->attach($resolver, $triggers);
	}

	/**
	 * Returns the number of triggers attached to the given resolver. Does NOT implicitly attach.
	 * Will return -1 if the resolver given is NOT attached.
	 *
	 * @param IResolver $resolver
	 * @return int
	 */
	public function getTriggerCount($resolver)
    {
		if ($this->resolvers->contains($resolver)) {
			return $this->getData($resolver)->count();
		}

		return -1;
	}


    /**
     * @param TriggerInterface $trigger
     * @throws OutOfBoundsException
     * @return IResolver
     */
    public function getResolverByTrigger($trigger)
    {
        // see if this trigger is referenced
        if (!array_key_exists($trigger->getHash(), $this->resolversIndexedByTriggerHash)) {
            throw new OutOfBoundsException('Given trigger is not attached to any resolver');
        }

        // we know this trigger existed once, but its resolver has already been acknowledged
        if (null === $this->resolversIndexedByTriggerHash[$trigger->getHash()]) {
            throw new OutOfBoundsException('Resolver for this trigger has already been acknowledged');
        }

        return $this->resolversIndexedByTriggerHash[$trigger->getHash()];
    }


    /**
     * @return IResolver[]|SplObjectStorage
     */
    public function getAllResolvers()
    {
        return $this->resolvers;
    }

} 