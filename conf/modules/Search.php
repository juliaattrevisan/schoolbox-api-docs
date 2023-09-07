<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\Search\Api;
use Schoolbox\Search\Mock;
use Schoolbox\Search\User;
use Schoolbox\Search\Module as SearchModule;

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
        $module = new Api\Module($di);
        $module->registerModule('/api/search');

        $module = new User\Module($di);
        $module->registerModule('/search/user');

        $module = new SearchModule($di);
        $module->registerModule('/search');
    }
}
