<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Calendar\Api\ModuleChecker;
use Schoolbox\CampusMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Folder\Mapper;
use Schoolbox\UserMapper;

class Module extends BaseModule
{

    /**
     * @inheritdoc
     */
    protected $basename = 'calendar';


    protected function registerRoutes(): void
    {
        $this->register('GET', '/v2', 'getCalendar');
    }

    /**
     * {@inheritdoc}
     */
//    public function initRoutePermissions(Authorizer $authorizer)
//    {
//        $this->addRoutePermission($authorizer, '.*', new IsLoggedIn());
//    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @param ContainerInterface $injector
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'folderMapper'  => $this->getMapperFactory()->getLazy(Mapper::class),
            'campusMapper'  => $this->getMapperFactory()->getLazy(CampusMapper::class),
        ];
    }
}
