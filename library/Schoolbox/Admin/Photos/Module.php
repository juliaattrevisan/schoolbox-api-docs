<?php
/**
 * Course builder admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Photos;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Failure;
use Alaress\Authorizer\Rule;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;
use InvalidArgumentException;
use Schoolbox\Resource\Storage;
use Schoolbox\UserMapper;


class Module extends BaseModule
{
    protected $basename = 'adminPhotos';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'getIndex', $basePath, "/upload");
        $this->addGetRoute($router, 'getView', $basePath, "");
        $this->addPostRoute($router, 'postUpload', $basePath, "/upload");
        $this->addPostRoute($router, 'postDelete', $basePath, "/delete");
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
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'storageMapper' => $this->getMapperFactory()->getLazy(Storage\Mapper::class),
            'userMapper' => $this->getMapperFactory()->getLazy(UserMapper::class),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer) {

        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
             ->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser());
    }


}
