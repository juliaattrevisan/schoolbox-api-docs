<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule\Any;
use Alaress\Authorizer\Rule\HasRole;
use Alaress\Authorizer\Rule\IsLoggedIn;
use Alaress\Authorizer\Rule\IsSuperuser;
use Alaress\Authorizer\Rule\IsUserOrParentOfUser;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\CampusMapper;
use Schoolbox\Resource\Folder\Mapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'calendar';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        // Supported format DD-MM-YYYY
        // Explanation here: https://regex101.com/r/X8zFce/1
        $this->addGetRoute($router, 'getCalendar', $basePath, "{/selectedDate}")->addTokens([
            'selectedDate' => '([0-2][0-9]|(3)[0-1]\d+(-))(-)(((0)[0-9])|((1)[0-2]))(-)\d{4}'
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '.*', new IsLoggedIn())
        ->addRoutePermission(
            $authorizer,
            '.*',
            new Any(
                new IsSuperuser(),
                new HasRole(Role::STAFF),
                new IsUserOrParentOfUser(
                    $this->getExternalUser(),
                    UserMapper::getInstance(),
                    'id'
                )
            )
        );
    }

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
            'folderMapper'      => $this->getMapperFactory()->getLazy(Mapper::class),
            'campusMapper'      => $this->getMapperFactory()->getLazy(CampusMapper::class),
            'eventTypeMapper'   => $this->getMapperFactory()->getLazy(EventTypeMapper::class),
        ];
    }
}
