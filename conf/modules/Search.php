<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\Search\Mock;
use Schoolbox\Search\User;

/**
 * Module configuration for search.
 */
class Search implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $module = new Mock\Module($di);
        $module->registerModule('/searchv2');
        $module->init('/searchv2');

        $module = new User\Module($di);
        $module->registerModule('/search/user');
        $module->init('/search/user');
    }
}
