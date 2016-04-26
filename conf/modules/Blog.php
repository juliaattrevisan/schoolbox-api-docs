<?php
/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Config\Modules;


use Alaress\Application\Kernel;
use Schoolbox\Blog\Blog\Module as BlogModule;

class Blog implements ModuleConfigInterface
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

        $blogModule = new BlogModule($di);
        $blogModule->init('/blog');
    }
} 
