<?php

/**
 * Configuration for the Meriden-specific pastoral system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Meriden;

use Alaress\Application\Kernel;
use Custom\Meriden\Pastoral\Module;
use Config\Modules\ModuleConfigInterface;
use External_User;

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
        if (strtoupper(trim($di->get('config')->get('site_school_code'))) === 'MS') {
            $pastoralModule = new Module($di);
            $pastoralModule->init('/modules/custom/meriden/pastoral');
        }
    }
}
