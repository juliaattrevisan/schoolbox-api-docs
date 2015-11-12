<?php
/**
 * Custom chain loader for Twig templates.

 * Overrides template methods to first check if the module has been loaded, and
 * then attempt to load it from the dependency injector before checking whether
 * this exists or not.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Twig\Loader;

use Alaress\Module\Loader as ModuleLoader;
use Aura\Di;
use Twig_Loader_Chain;

class ChainLoader extends Twig_Loader_Chain
{
    /**
     * Module loader
     *
     * @var ModuleLoader
     */
    protected $moduleLoader;

    /**
     * Modules that have been loaded
     *
     * @var array
     */
    protected $loadedModules = [];

    /**
     * Constructor
     *
     * @param ModuleLoader $moduleLoader
     * @param array        $loaders
     */
    public function __construct(ModuleLoader $moduleLoader, array $loaders = array())
    {
        parent::__construct($loaders);
        $this->moduleLoader = $moduleLoader;
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        $this->moduleLoader->loadModule($name);
        return parent::exists($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getSource($name)
    {
        $this->moduleLoader->loadModule($name);
        return parent::getSource($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheKey($name)
    {
        $this->moduleLoader->loadModule($name);
        return parent::getCacheKey($name);
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($name, $time)
    {
        $this->moduleLoader->loadModule($name);
        return parent::isFresh($name, $time);
    }
}
