<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
        $this->loadModule($kernel, TourModule::class, "/tour");
    }
}