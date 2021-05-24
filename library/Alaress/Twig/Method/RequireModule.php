<?php

/**
 * Twig function for requiring a module.
 *
 * @deprecated Module requirements should now be automatically installed when required
 *             (@see `Alaress\Module\Loader` and classes which use it)
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Twig\Method;

use Alaress\Module\Module as BaseModule;
use Aura\Di;
use Twig\Error\RuntimeError;

class RequireModule
{
    /**
     * @var Di\ContainerInterface
     */
    protected $di;

    /**
     * Constructor
     *
     * @param Di\ContainerInterface $di A DI container, which contains the
     *                                  modules required.
     */
    public function __construct(Di\ContainerInterface $di)
    {
        $this->di = $di;
    }

    /**
     * Loads the module.
     *
     * @param  string $moduleName
     * @throws RuntimeError if the module doesn't exist or isn't an instance of
     *                            `\Alaress\Module\Module`.
     */
    public function __invoke($moduleName)
    {
        try {
            $module = $this->di->get('module:' . $moduleName);
        } catch (Di\Exception\ServiceNotFound $e) {
            throw new RuntimeError(sprintf('Module "%s" does not exist', $moduleName));
        }
        if (!$module instanceof BaseModule) {
            throw new RuntimeError(
                sprintf(
                    'Module "%s" is not an instance of "%s"',
                    $moduleName,
                    BaseModule::class
                )
            );
        }
        $module->initControllerDependencies();
    }
}
