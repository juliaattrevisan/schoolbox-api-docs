<?php
namespace Schoolbox\Admin\FeatureTours;

use \Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule\IsSuperuser;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\ContainerInterface;
use Twig\Error\LoaderError;

class Module extends BaseModule
{
    protected $basename = "featureTours";

    public function registerRoutes()
    {
        $route = '';

        $perms = new IsSuperuser();

        $this->register('GET', $route, 'render', $perms);

        $this->register('POST', $route, 'updateConfig', $perms);

    }

    /**
     * @param IChainLoader $templateLoader
     * @throws LoaderError
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
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class)
        );
    }
}