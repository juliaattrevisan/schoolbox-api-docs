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
    protected SplObjectStorage $resolvers;

    /** @var IResolver[] */
    protected array $resolversIndexedByTriggerHash = [];

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
     * @param IResolver        $resolver
     * @param TriggerInterface $trigger
     * @return void
     */
    public function attachTriggerToResolver(IResolver $resolver, TriggerInterface $trigger): void
    {
        if (!$this->resolvers->contains($resolver)) {
            $this->resolvers->attach($resolver, new SplObjectStorage());
        }

        /** @var SplObjectStorage $triggers */
        $triggers = $this->resolvers->offsetGet($resolver);
        $triggers->attach($trigger);
        $this->resolvers->attach($resolver, $triggers);
        $this->resolversIndexedByTriggerHash[$trigger->getEventObjectHash()] = $resolver;
    }


    /**
     * Detach an existing trigger (should be only one...) related to the event object & action of the given trigger
     *
     * @param TriggerInterface $trigger
     * @return int
     * @throws OutOfBoundsException
     */
    public function detachTrigger(TriggerInterface $trigger): int
    {
        // get resolver of the same object as the given trigger
        $resolver = $this->getResolverByTrigger($trigger);

        // clear the resolver from the list
        unset($this->resolversIndexedByTriggerHash[$trigger->getEventObjectHash()]);

        // detach this first, so we're not dependent on whether it resolves or not
        $this->resolvers->detach($resolver);

        try {
            $resolver->acknowledge();
        } catch (RuntimeException $e) {
            // noop: this message has already been acknowledged, so we can safely drop it
        }

        // this should return -1, which means that the resolver has been acknowledged
        return $this->getTriggerCount($resolver);
    }


    /**
     * Returns the number of triggers attached to the given resolver. Does NOT implicitly attach.
     * Will return -1 if the resolver given is NOT attached.
     *
     * @param IResolver $resolver
     * @return int
     */
    public function getTriggerCount(IResolver $resolver): int
    {
        if ($this->resolvers->contains($resolver)) {
            return $this->resolvers->offsetGet($resolver)->count();
        }

        return -1;
    }


    /**
     * @param TriggerInterface $trigger
     * @return IResolver
     * @throws OutOfBoundsException
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
     * @return SplObjectStorage
     */
    public function getAllResolvers(): SplObjectStorage
    {
        return $this->resolvers;
    }
}
