<?php

/**
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\Blog\Eportfolio\Module as EportfolioBlogModule;
use Schoolbox\Blog\Homepage\Module as BlogModule;

class Blog implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $blogModule = new BlogModule($di);
        $blogModule->registerModule('/blog');
        $blogModule->registerModule('/blog');
        $blogModule->init('/blog');

        $eportfolioBlogModule = new EportfolioBlogModule($di);
        $eportfolioBlogModule->registerModule('/eportfolio/blog');
        $eportfolioBlogModule->registerModule('/eportfolio/blog');
        $eportfolioBlogModule->init('/eportfolio/blog');
    }
}
