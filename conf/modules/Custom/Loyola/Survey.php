<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Config\Modules\Custom\Loyola;

use Config\ModuleLoader;
use Alaress\Application\Kernel;
use Custom\Loyola\Survey\Component\Eportfolio\Module as EportfolioComponentModule;
use Custom\Loyola\Survey\Component\Homepage\Module as HomepageComponentModule;
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

        if ($di->get('config')->get('site_location') == 'loyola') {
            $this->loadModule($kernel, SurveyModule::class, "/{$namespace}");
            $this->loadModule($kernel, EportfolioComponentModule::class, "/{$namespace}");
            $this->loadModule($kernel, HomepageComponentModule::class, "/{$namespace}");
        }
    }
}
