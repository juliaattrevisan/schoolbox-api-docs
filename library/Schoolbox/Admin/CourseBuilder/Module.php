<?php
/**
 * Course builder admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\CourseBuilder;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Failure;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\RoleMapper;

class Module extends BaseModule
{
    protected $basename = 'adminCourseBuilder';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'getIndex', $basePath, "{/tab}");
        $this->addPostRoute($router, 'postSave', $basePath, "/save");
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer) {

        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
             ->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser());
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector) {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'configMapper'           => $this->getMapperFactory()->getLazy(ConfigMapper::class),
                    'courseConfigMapper'     => $this->getMapperFactory()->getLazy(CourseConfigMapper::class),
                    'homepageTemplateMapper' => $this->getMapperFactory()->getLazy(HomepageTemplateMapper::class),
                    'roleMapper'             => $this->getMapperFactory()->getLazy(RoleMapper::class),
                    'folderMapper'           => $this->getMapperFactory()->getLazy(FolderMapper::class),
                ]
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader) {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
