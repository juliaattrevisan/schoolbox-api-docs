<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\Submenu\Module;

class Submenu implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $namespace = 'submenu';

        $module = new Module($di);
        $module->registerModule("/{$namespace}");
    }
}
