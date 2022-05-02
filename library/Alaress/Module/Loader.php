<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Module;

use LogicException;
use Psr\Container\ContainerInterface;

/**
 * A loader class for loading module's controller dependencies dynamically.
 */
class Loader implements LoaderInterface
{
    /**
     * DI container
     */
    private ContainerInterface $di;

    /**
     * Regular expression which extracts a module name out of $moduleString
     * in `$this->loadModule($moduleString)`.
     *
     * Must have a `(?P<module>)` capturing block to work.
     *
     * @var string
     */
    private string $moduleRegex;

    /**
     * Constructor
     *
     * @param ContainerInterface $di
     * @param string             $moduleRegex
     */
    public function __construct(ContainerInterface $di, string $moduleRegex)
    {
        $this->di          = $di;
        $this->moduleRegex = $moduleRegex;
    }

    /**
     * {@inheritdoc}
     */
    public function loadModule(string $moduleString)
    {
        if (preg_match($this->moduleRegex, $moduleString, $matches)) {
            $moduleName = $matches['module'];
            try {
//                $module = Module::retrieveInstalledModule($moduleName);
                $module = Module::buildModuleFromRegistration($this->di, $moduleName);
            } catch (LogicException $e) {
                // There was no module to load.
                // That's fine, as `addTemplatePath` can set a different
                // basename for a template folder.
                return;
            }
            $module->initControllerDependencies();
        }
    }
}
