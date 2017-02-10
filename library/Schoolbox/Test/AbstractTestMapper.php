<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
