<?php
namespace Config\Modules\Custom\Tps;

use Alaress\Application\Kernel;
use Custom\Btac\Grades\Module;
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
        if ($di->get('config')->get('site_location') == 'tps') {
            $module = new Module($di);
            // Override previous definition
            $module->initOverride('/learning/grades');
        }
    }
}
