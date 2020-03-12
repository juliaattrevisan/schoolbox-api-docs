<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\Notification\Instance;


use SplObjectStorage;

class InstanceBundle
{

    /** @var SplObjectStorage|Instance[] */
    protected $splStore;


    /**
     * Create a new bundle
     */
    public function __construct()
    {
        $this->splStore = new SplObjectStorage();
    }


    /**
     * Binds an instance to its outer topic (something like "role.$roleId" or "service.homepage.$catId")
     *
     * @param string   $target
     * @param Instance $instance
     * @param string   $delivery The delivery method:
     *                           one of the `Instance::DELIVERY_*` constants.
     *                           Currently, setting a delivery method only works
     *                           for instances targeted directly to a user,
     *                           because the only instant or alert queue that a
     *                           user listens to is their own personal queue.
     *
     */
    protected function bind($target, $instance, string $delivery = Instance::DELIVERY_NOTIFY)
    {

        // If we accidentally passed a builder object (ie. forgot to call build()),
        // just accept it and build the instance on the fly.
        if ($instance instanceof BuilderInterface) {
            $instance = $instance->build();
        }

        if (!$this->splStore->contains($instance)) {
            $this->splStore->attach($instance, []);
        }

        $topics = $this->splStore->offsetGet($instance);

        // iterate the instance's topics, and prepend the given target and delivery
        foreach ($instance->getTopics() as $topic) {
            $destination                = "$delivery.$target.$topic";
            $topics[sha1($destination)] = $destination;
        }

        $this->splStore->attach($instance, $topics);
    }


    /**
     * Returns the number of notification instances this bundle contains
     * Each instance will have at least one destination topic registered to it
     *
     * @return int
     */
    public function count()
    {
        return $this->splStore->count();
    }


    /**
     * Returns whether or not this bundle contains notifications
     *
     * @return bool
     */
    public function isEmpty()
    {
        return !$this->count();
    }


    /**
     * Returns all instances contained, indexed by destination
     *
     * @param int $timestampOverride
     * @return Instance[]
     */
    public function getAll($timestampOverride = null)
    {
        $instances = [];
        foreach ($this->splStore as $instance) {
            if (!is_null($timestampOverride)) {
                $instance->setTime($timestampOverride);
            }
            $destinations = $this->splStore->offsetGet($instance);
            foreach ($destinations as $destination) {
                // index by destination
                $instances[$destination] = $instance;
            }
        }
        return $instances;
    }
}
