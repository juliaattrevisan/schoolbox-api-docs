<?php
/**
 * A loader class for loading module's controller dependencies dynamically.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Module;

use Aura\Di\ContainerInterface;

class Loader implements LoaderInterface
{
    /**
     * The dependency injection container.
     *
     * @var ContainerInterface
     */
    protected $injector;

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
     * @param ContainerInterface $injector
     * @param string             $moduleRegex
     */
    public function __construct(ContainerInterface $injector, $moduleRegex)
    {
        $this->injector    = $injector;
        $this->moduleRegex = $moduleRegex;
    }

    /**
     * {@inheritdoc}
     */
    public function loadModule($moduleString)
    {
        if (preg_match($this->moduleRegex, $moduleString, $matches)) {
            $moduleName = $matches['module'];
            if (empty($this->loadedModules[$moduleName])) {
                if ($this->injector->has('module:'.$moduleName)) {
                    $module = $this->injector->get('module:'.$moduleName);
                    if ($module instanceof Module) {
                        $module->initControllerDependencies();
                    }
                }
                // Don't try to init again, even if it wasn't found
                $this->loadedModules[$moduleName] = true;
            }
        }
    }
}
