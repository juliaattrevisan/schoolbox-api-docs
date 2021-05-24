<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Blog\Eportfolio\Module as EportfolioBlogModule;
use Schoolbox\Blog\Homepage\Module as BlogModule;

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

        $eportfolioBlogModule = new EportfolioBlogModule($di);
        $eportfolioBlogModule->init('/eportfolio/blog');
    }
}
