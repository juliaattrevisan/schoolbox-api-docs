<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Realtime\Routing\Subscription;


use Alaress\Routing\IRouter;
use InvalidArgumentException;

class Manager
{
    /** @var IRouter */
    protected $router;

    /**
     * @param IRouter $router
     */
    public function __construct(IRouter $router)
    {
        $this->router = $router;
    }

    /**
     * @param string $topic
     * @throws InvalidArgumentException
     * @return array
     */
    public function getSubscribers($topic)
    {
        return array_keys(array_filter($this->router->get($topic)));
    }

}
