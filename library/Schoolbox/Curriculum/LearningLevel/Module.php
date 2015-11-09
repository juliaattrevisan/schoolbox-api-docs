<?php
/**
 * Definition for the curriculum learning level submodule.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum\LearningLevel;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Curriculum\Source;
use Schoolbox\Permission\Access\UserAccess;

class Module extends BaseModule
{
    protected $basename = 'curriculumLearningLevel';

    /**
     * So far, the only action is "create"
     *
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addPostRoute($router, 'create', $basePath, '');
    }

    /**
     * Only users with write permission to the curriculum service may import.
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $authorizer->addRule('/^curriculumLearningLevel\\..*/', new Rule\IsLoggedIn());
        $authorizer->addRule(
            '/^curriculumLearningLevel\\..*/',
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
    public function initControllers(ContainerInterface $injector)
    {
        $injector->params[Controller::class]['mapper']
            = $injector->lazyNew(Mapper::class);
        $injector->params[Controller::class]['sourceMapper']
            = $injector->lazyNew(Source\Mapper::class);
        $injector->set(
            'controller:curriculumLearningLevel',
            $injector->lazyNew(Controller::class)
        );
    }
}
