<?php
/**
 * Custom chain loader for Twig templates.
 *
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
use Twig\Loader\ChainLoader as TwigChainLoader;
use Twig\Loader\LoaderInterface;

class ChainLoader implements IChainLoader
{
    /**
     * Twig loader
     *
     * @var TwigChainLoader
     */
    protected $twigLoader;

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
     * @param ModuleLoader    $moduleLoader
     * @param TwigChainLoader $twigLoader
     */
    public function __construct(ModuleLoader $moduleLoader, TwigChainLoader $twigLoader)
    {
        $this->moduleLoader = $moduleLoader;
        $this->twigLoader   = $twigLoader;
    }

    /**
     * Add a loader
     *
     * @param LoaderInterface $loader
     */
    public function addLoader(LoaderInterface $loader)
    {
        $this->twigLoader->addLoader($loader);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        $this->moduleLoader->loadModule($name);
        return $this->twigLoader->exists($name);
    }

//    /**
//     * {@inheritdoc}
//     */
//    public function getSource($name)
//    {
//        $this->moduleLoader->loadModule($name);
//        return $this->twigLoader->getSource($name);
//    }

    /**
     * {@inheritdoc}
     */
    public function getCacheKey($name)
    {
        $this->moduleLoader->loadModule($name);
        return $this->twigLoader->getCacheKey($name);
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($name, $time)
    {
        $this->moduleLoader->loadModule($name);
        return $this->twigLoader->isFresh($name, $time);
    }

    /**
     * {@inheritdoc}
     */
    public function getSourceContext($name)
    {
        $this->moduleLoader->loadModule($name);
        return $this->twigLoader->getSourceContext($name);
    }
}
