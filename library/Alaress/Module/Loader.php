<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Module;

/**
 * A loader class for loading module's controller dependencies dynamically.
 */
class Loader implements LoaderInterface
{
    /**
     * Modules that have already been loaded. Don't try to load them again.
     *
     * @var array
     */
    protected $loadedModules = [];

    /**
     * Regular expression which extracts a module name out of $moduleString
     * in `$this->loadModule($moduleString)`.
     *
     * Must have a `(?P<module>)` capturing block to work.
     *
     * @var string
     */
    protected $moduleRegex;

    /**
     * Constructor
     *
     * @param string $moduleRegex
     */
    public function __construct($moduleRegex)
    {
        $this->moduleRegex = $moduleRegex;
    }

    /**
     * {@inheritdoc}
     */
    public function loadModule(string $moduleString)
    {
        if (preg_match($this->moduleRegex, $moduleString, $matches)) {
            $moduleName = $matches['module'];
            $module     = Module::retrieveInstalledModule($moduleName);
            $module->initControllerDependencies();
//            if (empty($this->loadedModules[$moduleName])) {
//                if ($this->injector->has('module:' . $moduleName)) {
//                    $module = $this->injector->get('module:' . $moduleName);
//                    if ($module instanceof Module) {
//                        $module->initControllerDependencies();
//                    }
//                }
//                // Don't try to init again, even if it wasn't found
//                $this->loadedModules[$moduleName] = true;
//            }
        }
    }
}
