<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Filter;

use Alaress\Async\Notification\Instance\Instance;
use Schoolbox\User;
use SplObjectStorage;

class FilterBundle implements FilterInterface
{

    /** @var SplObjectStorage|FilterInterface[] */
    protected $filters;

    /**
     * FilterComposite constructor.
     *
     * @param FilterInterface[] $filters
     */
    public function __construct(array $filters)
    {
        $this->filters = new SplObjectStorage();
        $this->attachEach($filters);
    }


    /**
     * @param FilterInterface $filter
     * @return self
     */
    public function attach(FilterInterface $filter)
    {
        $this->filters->attach($filter);
        return $this;
    }


    /**
     * @param FilterInterface[] $filters
     * @return self
     */
    public function attachEach(array $filters)
    {
        foreach ($filters as $filter) {
            $this->attach($filter);
        }

        return $this;
    }


    /**
     * Returns true if this message is considered valid, or false if it should be dismissed
     *
     * @param Instance $message
     * @param User     $user
     * @throws FilterException
     * @return bool
     */
    public function validate(Instance $message, User $user)
    {
        foreach ($this->filters as $filter) {
            $filter->validate($message, $user);
        }

        return true;
    }
}
