<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Config\Modules;


use Alaress\Application\Kernel;
use Schoolbox;

class Resource implements ModuleConfigInterface
{

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();

        $browserModule = new Schoolbox\Resource\Browser\Resources\Module($di);

        $browserModule->init('/resources');

        $myBrowserModule = new Schoolbox\Resource\Browser\MyFiles\Module($di);
        $myBrowserModule->init('/my-files');

    }

} 
