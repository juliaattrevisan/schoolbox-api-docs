<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Help;

use Config\ModuleLoader;
use Custom\Help\Marketo\Component\Dashboard\Module as MarketoModule;
use Psr\Container\ContainerInterface;

class Marketo extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $namespace = 'marketo';

        $this->loadModule($di, MarketoModule::class, "/modules/custom/help/{$namespace}");
    }
}
