<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Search\Mock;

use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Faker;

/**
 * The module for the mock version of the search API.
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'search';


    /**
     * @inheritDoc
     */
    protected function registerRoutes(): void
    {
        $this->register('GET', '', 'getSearch');
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * @inheritDoc
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        // Build a Faker instance and shove it in
        $injector->params[Controller::class] = [
            'faker' => $injector->lazy(static function () {
                // Surprised that undefinedClass::class evaluates to "undefinedClass"
                if (class_exists(Faker\Factory::class)) {
                    return Faker\Factory::create();
                }
                return null;
            }),
        ];
    }
}
