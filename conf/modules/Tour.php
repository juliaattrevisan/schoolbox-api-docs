<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Schoolbox\Tour\Module as TourModule;

class Tour extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();

        if ($di->get('config')->get('tours_enabled')) {
            $this->loadModule($kernel, TourModule::class, "/tour");
        }
    }
}
