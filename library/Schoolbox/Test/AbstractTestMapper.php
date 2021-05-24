<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Test;

use Schoolbox\Mapper;

abstract class AbstractTestMapper extends Mapper
{

    /**
     * Overrides constructor as public, so that new instances can be created
     * without going through getInstance().
     */
    public function __construct()
    {
        parent::__construct();
        $this->setConfigLoader(new \ConfigFallback());
    }
}
