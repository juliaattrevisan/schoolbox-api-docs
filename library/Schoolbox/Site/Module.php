<?php
/**
 * @author      James Leckie <james.leckie@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
namespace Schoolbox\Site;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use InvalidArgumentException;
use Twig_Error_Loader;
use ReCaptcha;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'site';

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function registerRoutes()
    {
        $this->register('GET', '/feedback', 'getFeedback', null, null);
        $this->register('GET', '/feedback/thank-you', 'getFeedbackReceipt', null, null);

        $this->register('GET', '/policy', 'getPolicy', null, null);
        //$this->register('GET', '/login', 'getLogin', null, null);
    }

    /**
     * Override with any steps required for a controller and its routes
     *
     * You should use this method to initialize any mappers or other objects
     * which the controller needs, but are too heavy to initialize at module
     * init time.
     *
     * @param Container|ContainerInterface $injector The dependency injection container.
     * @throws InvalidArgumentException
     */
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'errorMapper' => $this->getMapperFactory()->getLazy(\Schoolbox\Error\Mapper::class),
            'reCaptcha'   => new ReCaptcha($this->getConfig()->get('recaptcha_public_api_key'), $this->getConfig()->get('recaptcha_private_api_key'))
        ];
    }

    /**
     * @param IChainLoader $templateLoader
     * @throws Twig_Error_Loader
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Initializes controller classes into the dependency injector.
     *
     * @param ContainerInterface $injector The dependency injection container.
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class));
    }
}
