<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Async\AMQP;

class AMQPExchange
{

    const TYPE_TOPIC     = 'topic';
    const TYPE_BROADCAST = 'broadcast';

    /** @var string */
    protected $name;

    /** @var string */
    protected $type;


    /**
     * @param string $name
     * @param string $type
     */
    public function __construct($name, $type)
    {
        $this->name = $name;
        $this->type = $type;
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}
