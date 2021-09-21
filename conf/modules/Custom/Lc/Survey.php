<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Lc;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Custom\Lc\Survey\Component\Eportfolio\Module as EportfolioComponentModule;
use Custom\Lc\Survey\Module as SurveyModule;

class Survey extends ModuleLoader
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
        $this->loadModule($kernel, EportfolioComponentModule::class, "/{$namespace}");
    }
}
