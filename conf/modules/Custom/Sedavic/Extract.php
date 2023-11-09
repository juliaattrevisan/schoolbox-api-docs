<?php

/**
 * help.schoolbox.com.au
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Sedavic;

use Config\ModuleLoader;
use Custom\Sedavic\Extract\Module as ExtractModule;
use Psr\Container\ContainerInterface;

class Extract extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, ExtractModule::class, '/extract');
    }
}
