<?php
/**
 * Configuration for De La Salle's assessment csv export
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// TODO: Renamespace this for production
namespace Config\Modules\Custom\Demo;

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

        // TODO: Re-enable this for production
        //if ($di->get('config')->get('site_location') == 'btac') {
            $module = new Module($di);
            $module->init('/modules/custom/btac/grades');
        //}
    }
}