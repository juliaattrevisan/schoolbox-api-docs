<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
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
    public function attachTriggerToResolver(IResolver $resolver, TriggerInterface $trigger): void
    {
        $data = $this->getData($resolver);
        $data->attach($trigger);
        $this->setData($resolver, $data);
        $this->resolversIndexedByTriggerHash[$trigger->getEventObjectHash()] = $resolver;
    }


    /**
     * Detach an existing trigger (should be only one...) related to the event object & action of the given trigger
     *
     * @param TriggerInterface $trigger
     * @throws OutOfBoundsException
     * @return int
     */
    public function detachTrigger(TriggerInterface $trigger): int
    {
        // get resolver of the same object as the given trigger
        $resolver = $this->getResolverByTrigger($trigger);

        // get an existing trigger (should be only one) from the resolver
        // in case of rescheduling, the returned trigger will be the one to be canceled
        $triggers = $this->getTriggersByResolver($resolver);

        // clear the resolver from the list
        unset($this->resolversIndexedByTriggerHash[$trigger->getEventObjectHash()]);

        // remove the attached trigger from the resolver.
        $triggers->removeAll($triggers);
        $this->setData($resolver, $triggers);

        if ($this->getTriggerCount($resolver) === 0) {
            // detach this first, so we're not dependent on whether it resolves or not
            $this->resolvers->detach($resolver);


            // TODO: remove the trigger from redis

            try {
                $resolver->acknowledge();
            } catch (RuntimeException $e) {
                // noop: this message has already been acknowledged, so we can safely drop it
            }
        }

        // this should return -1, which means that the resolver has been acknowledged
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
    public function getResolverByTrigger(TriggerInterface $trigger): IResolver
    {
        // see if this event object & action of the trigger is referenced
        if (!array_key_exists($trigger->getEventObjectHash(), $this->resolversIndexedByTriggerHash)) {
            throw new OutOfBoundsException("Given trigger is not attached to any resolver: key {$trigger->getEventObjectHash()}");
        }

        return $this->resolversIndexedByTriggerHash[$trigger->getEventObjectHash()];
    }


    /**
     * @return IResolver[]|SplObjectStorage
     */
    public function getAllResolvers()
    {
        return $this->resolvers;
    }
}
