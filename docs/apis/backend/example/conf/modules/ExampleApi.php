<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\Example\Api\Module;

/**
 * Module configuration for module API.
 */
class ExampleApi implements ModuleConfigInterface
{
    /**
     * Configure the module.
     *
     * Usually, API endpoints will have the word `api` somewhere in their name,
     * or something else which identifies them as APIs.
     */
    public function configure(ContainerInterface $di): void
    {
        $module = new Module($di);
        $module->registerModule("/example/api");
    }
}
