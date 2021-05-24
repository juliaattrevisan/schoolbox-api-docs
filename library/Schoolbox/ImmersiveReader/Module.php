<?php

/**
 * @author      James Leckie
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\ImmersiveReader;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Schoolbox\Config\ConfigMapper;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'immersiveReader';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function registerRoutes()
    {
        $isLoggedIn = new Rule\All(
            new Rule\IsConfigOn('office365_immersive_reader_name', $this->getConfig()),
            new Rule\IsLoggedIn()
        );

        $this->register('GET', '/getAuth', 'getTokenAndSubdomain', $isLoggedIn, null);
    }

    /**
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                        'configMapper'           => $this->getMapperFactory()->getLazy(ConfigMapper::class)
                ]
            )
        );
    }
}
