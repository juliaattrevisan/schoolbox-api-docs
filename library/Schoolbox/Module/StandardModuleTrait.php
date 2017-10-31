<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Module;


use Aura\Di\ContainerInterface;
use Twig_Error_Loader;
use Alaress\Twig\Loader\IChainLoader;

/**
 * Class StandardModuleTrait
 *
 * This trait exists to simplify the most common Module functionality, specifically obtaining template path and Controller locations
 *
 * @package Schoolbox\Module
 */
trait StandardModuleTrait
{

    /**
     * Helper method for adding a template path for this component's basename.
     *
     * @param  IChainLoader $templateLoader
     * @param  string            $dir The directory to use as the template path
     * @param string             $basename
     * @return $this
     */
    protected abstract function addTemplatePath(IChainLoader $templateLoader, $dir, $basename = '');


    /**
     * @return string
     */
    protected abstract function getBasename();


    /**
     * @return string
     */
    protected function getControllerClassName()
    {
        // adjacency is assumed
        return str_replace('Module', 'Controller', __CLASS__);
    }

    /**
     * @param IChainLoader $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $replacements = [
            '\\'     => '/',
            'Module' => 'templates'
        ];

        // obtain the standard template directory from the Module classname
        $tpl = SITE_root . '/library/' . str_replace(array_keys($replacements), array_values($replacements), __CLASS__);

        $this->addTemplatePath($templateLoader, $tpl);
    }

    /**
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->getBasename(),
            $injector->lazyNew($this->getControllerClassName())
        );
    }

}
