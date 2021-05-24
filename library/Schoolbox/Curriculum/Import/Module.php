<?php

/**
 * Definition for the curriculum import submodule.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Curriculum\Import;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Permission\Access\UserAccess;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Curriculum;

class Module extends BaseModule
{
    protected $basename = 'curriculumImport';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'browse', $basePath, '');
        $this->addPostRoute($router, 'create', $basePath, '');
        $this->addGetRoute($router, 'learningLevels', $basePath, '/learningLevels');
        $this->addGetRoute($router, 'outcomes', $basePath, '/outcomes');
    }

    /**
     * Only users with write permission to the curriculum service may import.
     * @param Authorizer $authorizer
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                '.*',
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasServicePermission(
                        UserAccess::SERVICE_CURRICULUM,
                        UserAccess::ACCESS_WRITE
                    )
                )
            );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class)
        );
        $injector->setters[Controller::class]['setSourceMapper']
            = $this->getMapperFactory()->getLazy(Curriculum\Source\Mapper::class);
        $injector->setters[Controller::class]['setAreaMapper']
            = $this->getMapperFactory()->getLazy(Curriculum\LearningArea\Mapper::class);
        $injector->setters[Controller::class]['setLevelMapper']
            = $this->getMapperFactory()->getLazy(Curriculum\LearningLevel\Mapper::class);
        $injector->setters[Controller::class]['setNodeMapper']
            = $this->getMapperFactory()->getLazy(Curriculum\Node\Mapper::class);
    }
}
