<?php
/**
 * help.schoolbox.com.au
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Config\Modules\Custom\Help;

use Alaress\Application\Kernel;
use Config\ModuleLoader;
use Custom\Help\Nesa\Module as NesaModule;

class Nesa extends ModuleLoader {

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel) {
        $this->loadModule($kernel, NesaModule::class, '/help/nesa');
    }
}
