<?php

namespace Schoolbox\Admin\FeatureTours;

use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule\IsSuperuser;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Psr\Container\ContainerInterface;
use Schoolbox\Tour\Mapper as TourMapper;
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
     * @inheritdoc
     */
    public function buildController(ContainerInterface $container): Base
    {
        return new Controller($this->getMapperFactory()->get(TourMapper::class));
    }
}
