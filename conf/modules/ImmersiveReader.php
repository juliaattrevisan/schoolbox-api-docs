<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Schoolbox\ImmersiveReader\Module as ImmersiveReaderModule;

class ImmersiveReader extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $namespace = 'immersive-reader';
        $this->loadModule($di, ImmersiveReaderModule::class, "/{$namespace}");
    }
}
