<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Schoolbox\Survey\Module as SurveyModule;
use Schoolbox\Survey\Component\Eportfolio\Module as EportfolioModule;

class Survey extends ModuleLoader implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $namespace = 'survey';

        $this->loadModule($kernel, SurveyModule::class, "/{$namespace}");
        $this->loadModule($kernel, EportfolioModule::class, "/{$namespace}");
    }
}