<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Config\Modules\Custom\Stk;

use Alaress\Application\Kernel;
use Custom\Stk\NCEA\Module as NCEAModule;
use Config\Modules\ModuleConfigInterface;

class NCEA implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();

        if ($di->get('config')->get('site_location') === 'stk') {
            $module = new NCEAModule($di);
            $module->init('/modules/custom/stk/ncea');
        }
    }
}
