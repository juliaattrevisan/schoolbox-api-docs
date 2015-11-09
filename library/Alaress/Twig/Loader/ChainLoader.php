<?php
/**
 * Class description here
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Twig\Loader;

use Alaress\Module\Module as BaseModule;
use Aura\Di;
use Twig_Loader_Chain;

class ChainLoader extends Twig_Loader_Chain
{
    /**
     * @var Di\ContainerInterface
     */
    protected $di;

    /**
     * Modules that have been loaded
     *
     * @var array
     */
    protected $loadedModules = [];

    /**
     * Constructor
     *
     * @param Di\ContainerInterface $di
     * @param array                 $loaders
     */
    public function __construct(Di\ContainerInterface $di, array $loaders = array())
    {
        parent::__construct($loaders);
        $this->di = $di;
    }

    /**
     * If this template's module hasn't yet been loaded, attempt to load it
     * before checking whether this exists or not.
     *
     * @param string $name
     */
    protected function loadModuleForTemplate($name)
    {
        if (preg_match('[^@(?P<module>.*?)/]', $name, $matches)) {
            $moduleName = $matches['module'];
            if (empty($this->loadedModules[$moduleName])) {
                if ($this->di->has('module:'.$moduleName)) {
                    $module = $this->di->get('module:'.$moduleName);
                    if ($module instanceof BaseModule) {
                        $module->initControllerDependencies();
                    }
                }
                // Don't try to init again, even if it wasn't found
                $this->loadedModules[$moduleName] = true;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name)
    {
        $this->loadModuleForTemplate($name);
        return parent::exists($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getSource($name)
    {
        $this->loadModuleForTemplate($name);
        return parent::getSource($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheKey($name)
    {
        $this->loadModuleForTemplate($name);
        return parent::getCacheKey($name);
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($name, $time)
    {
        $this->loadModuleForTemplate($name);
        return parent::isFresh($name, $time);
    }
}
