<?php
namespace Config\Modules\Custom\Peninsula;

use Alaress\Application\Kernel;
use Custom\Peninsula\Grades\Module;
use Config\Modules\ModuleConfigInterface;

class Grades implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     *
     * @return void
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();
        if ($di->get('config')->get('site_location') == 'peninsula') {
            $module = new Module($di);
            // Override previous definition
            $module->initOverride('/learning/grades');
        }
    }
}
