<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\InstanceRegistry;

use Alaress_Cache_Abstract;

class Cache extends Alaress_Cache_Abstract
{

    /**
     * A prefix for cached filenames
     *
     * @var string
     */
    protected $cacheFilePrefix = 'instance_registry';

    /**
     * Hook method
     *
     * @return void
     */
    protected function setup()
    {
        $this->lockTime = $this->lockTimeMultiplier * $this->cachedTime;
    }

    /**
     * Constructor
     *
     * @param int|null $cachedTime cache duration in seconds
     */
    public function __construct(?int $cachedTime)
    {
        $this->cachedTime ??= $cachedTime;
        parent::__construct();
    }
}
