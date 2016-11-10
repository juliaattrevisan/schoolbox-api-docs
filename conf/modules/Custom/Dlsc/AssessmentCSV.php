<?php
/**
 * Configuration for De La Salle's assessment csv export
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Config\Modules\Custom\Dlsc;

use Alaress\Application\Kernel;
use Custom\Dlsc\AssessmentCSV\Module;
use Config\Modules\ModuleConfigInterface;

class AssessmentCSV implements ModuleConfigInterface
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

        //if ($di->get('config')->get('site_location') == 'dlsc') {
            $module = new Module($di);
            $module->init('/modules/custom/dlsc/assessmentcsv');
        //}
    }
}