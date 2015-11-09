<?php
/**
 * Definition for the curriculum import submodule.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum\Import;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Permission\Access\UserAccess;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

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
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermissionForBasename($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermissionForBasename(
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
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePathForBasename($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew('Schoolbox\\Curriculum\\Import\\Controller')
        );
        $injector->setters['Schoolbox\\Curriculum\\Import\\Controller']['setSourceMapper']
            = $injector->lazyNew('Schoolbox\\Curriculum\\Source\\Mapper');
        $injector->setters['Schoolbox\\Curriculum\\Import\\Controller']['setAreaMapper']
            = $injector->lazyNew('Schoolbox\\Curriculum\\LearningArea\\Mapper');
        $injector->setters['Schoolbox\\Curriculum\\Import\\Controller']['setLevelMapper']
            = $injector->lazyNew('Schoolbox\\Curriculum\\LearningLevel\\Mapper');
        $injector->setters['Schoolbox\\Curriculum\\Import\\Controller']['setNodeMapper']
            = $injector->lazyNew('Schoolbox\\Curriculum\\Node\\Mapper');
    }
}
