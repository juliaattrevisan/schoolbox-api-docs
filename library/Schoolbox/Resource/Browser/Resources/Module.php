<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\Resources;


use Schoolbox\Resource\Browser\AbstractModule;

class Module extends AbstractModule
{

    /**
     * @return string
     */
    protected function getControllerClass()
    {
        return Controller::class;
    }

} 