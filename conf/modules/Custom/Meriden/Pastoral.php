<?php
/**
 * Configuration for the Meriden-specific pastoral system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Config\Modules\Custom\Meriden;

use Alaress\Application\Kernel;
use Custom\Meriden\Pastoral\Module;
use Config\Modules\ModuleConfigInterface;

class Pastoral implements ModuleConfigInterface
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
        if ($di->get('config')->get('site_location') == 'meriden') {
            $pastoralModule = new Module($di);
            $pastoralModule
                ->setExternalDb($di->newInstance('\\External_User'))
                ->setUserMapper($di->newInstance('\\Schoolbox\\UserMapper'))
            ;
            $pastoralModule->init('/modules/custom/meriden/pastoral');
        }
    }
}
