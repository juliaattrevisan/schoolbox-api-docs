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
use Alaress\Web\Controller\Base;
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
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return (new Controller())
            ->setSourceMapper($this->getMapperFactory()->get(Curriculum\Source\Mapper::class))
            ->setAreaMapper($this->getMapperFactory()->get(Curriculum\LearningArea\Mapper::class))
            ->setLevelMapper($this->getMapperFactory()->get(Curriculum\LearningLevel\Mapper::class))
            ->setNodeMapper($this->getMapperFactory()->get(Curriculum\Node\Mapper::class));
    }
}
