<?php

/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace EOTC\ActivityLog;

use Schoolbox\Collection;

class Registry
{

    /**
     * A Collection of observers
     *
     * @var Collection
     */
    private $registry = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setRegistry(new Collection());
    }

    /**
     * Delegation method
     *
     * @param Log $item
     */
    public function add($item)
    {
        $this->getRegistry()->add($item);
    }

    /**
     * All observers - notify!
     */
    public function notify()
    {
        foreach ($this->getRegistry() as $current) {
            $current->notify();
        }
    }

    /* Getters/Setters
     */

    /**
     * Set registry
     *
     * @param Collection $registry
     */
    private function setRegistry($registry)
    {
        $this->registry = $registry;
    }

    /**
     * Get registry
     *
     * @return Collection
     */
    private function getRegistry()
    {
        return $this->registry;
    }
}
