<?php
/**
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Weather;

use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Alaress\Twig\Loader\IChainLoader;

class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'weather';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        $this->register('GET', '/weather', 'getWeatherDetails'); // Weather details page
    }


    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
