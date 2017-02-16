<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Alaress\Module\Module;
use Schoolbox\Config\Loader as ConfigLoader;

interface ModuleConfigInterface {

	/**
	 * Run configuration for the given module
	 *
	 * @param Kernel $kernel
	 * @return void
	 */
	public function configure($kernel);
}

class ModuleLoader {

    /**
     * @param string $className Name of module class to load
     * @param $path
     * @param Kernel $kernel
     * @return Module
     */
    protected function loadModule($className, $path, $kernel) {

        $di = $kernel->getDependencyInjector();

        $schoolCode = $di->get('config')->get('site_location');
        $customModuleClass = str_replace('\\Module', '\\Custom\\' . ucfirst($schoolCode) . '\\Module', $className);

        /** @var Module $module */

        if (class_exists($customModuleClass)) {
            $module = new $customModuleClass($di);
        } else {
            $module = new $className($di);
        }
        $module->init($path);
        return $module;
    }
}