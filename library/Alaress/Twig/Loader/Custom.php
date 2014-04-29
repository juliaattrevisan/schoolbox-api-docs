<?php
/**
 * Loader for templates for custom skins.
 *
 * It
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Alaress\Twig\Loader;

use Schoolbox\IConfigLoader;
use Twig_LoaderInterface;
use Twig_ExistsLoaderInterface;
use Twig_Loader_Filesystem;

class Custom implements Twig_LoaderInterface, Twig_ExistsLoaderInterface {

    /**
     * @var IConfigLoader
     */
    protected $configLoader;

    /**
     * @var Twig_Loader_Filesystem
     */
    protected $twigLoader;

    public function __construct($paths) {
        // skin path: set in config
        $this->setTwigLoader(new Twig_Loader_Filesystem($paths));
    }

    /**
     * Converts a name from the format the new Twig loader uses
     * (which is its full path) to one that is in the 'templates/' folder.
     *
     * @example /var/www/schoolbox/library/Schoolbox/ResourceBooking/Category/Controller/templates/browse.twig becomes
     *          Schoolbox/ResourceBooking/Category/Controller/templates/browse.twig
     *
     * @param  string $name
     * @return string
     */
    protected function mangleName($name) {
        return str_replace($this->getConfigLoader()->get('siteRoot').'/library/', '', $name);
    }

    /**
     * {@inheritdoc}
     */
    public function exists($name) {
        return $this->getTwigLoader()->exists($this->mangleName($name));
    }

    /**
     * {@inheritdoc}
     */
    public function getSource($name) {
        return $this->getTwigLoader()->getSource($this->mangleName($name));
    }

    /**
     * {@inheritdoc}
     */
    public function getCacheKey($name) {
        return $this->getTwigLoader()->getCacheKey($this->mangleName($name));
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($name, $time) {
        return $this->getTwigLoader()->isFresh($this->mangleName($name), $time);
    }

    /**
     * @param  Twig_Loader_Filesystem $loader
     * @return $this
     */
    public function setTwigLoader($loader) {
        $this->twigLoader = $loader;
        return $this;
    }

    /**
     * @return Twig_Loader_Filesystem
     */
    public function getTwigLoader() {
        return $this->twigLoader;
    }

    /**
     * @param  IConfigLoader $configLoader
     * @return $this
     */
    public function setConfigLoader($configLoader) {
        $this->configLoader = $configLoader;
        return $this;
    }

    /**
     * @return IConfigLoader
     */
    public function getConfigLoader() {
        return $this->configLoader;
    }
}
