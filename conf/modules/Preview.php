<?php
/**
 * Preview
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Preview\Module;

class Preview implements ModuleConfigInterface {

	/**
	 * Run configuration for the given module
	 *
	 * @param Kernel $kernel
	 * @return void
	 */
	public function configure($kernel) {
		$di = $kernel->getDependencyInjector();

        $module = new Module($di);
        $module->init('/preview');
	}
}
