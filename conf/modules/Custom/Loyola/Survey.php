<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Loyola;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Custom\Loyola\Survey\Component\Eportfolio\Module as EportfolioComponentModule;
use Config\Modules\ModuleConfigInterface;
use Custom\Loyola\Survey\Module as SurveyModule;

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

        $di = $kernel->getDependencyInjector();

        if (strtoupper(trim($di->get('config')->get('site_school_code'))) === 'LC') {
            $this->loadModule($kernel, SurveyModule::class, "/{$namespace}");
            $this->loadModule($kernel, EportfolioComponentModule::class, "/{$namespace}");
        }
    }
}
