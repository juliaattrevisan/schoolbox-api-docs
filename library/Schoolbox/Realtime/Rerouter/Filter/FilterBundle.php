<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Rerouter\Filter;


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
    public function attach(FilterInterface $filter) {
        $this->filters->attach($filter);
        return $this;
    }


    /**
     * @param FilterInterface[] $filters
     * @return self
     */
    public function attachEach(array $filters) {
        foreach ($filters as $filter) {
            $this->attach($filter);
        }

        return $this;
    }


    /**
     * Returns true if this message is considered valid, or false if it should be dismissed
     *
     * @param Instance $message
     * @param array    $formatted
     * @param User     $user
     * @throws FilterException
     * @return bool
     */
    public function validate($message, $formatted, $user)
    {
        foreach ($this->filters as $filter) {
            $filter->validate($message, $formatted, $user);
        }

        return true;
    }

}
