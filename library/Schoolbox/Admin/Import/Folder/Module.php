<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Import\Folder;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Error;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\RoleMapper;

/**
 * Class Module
 *
 * Bulk add folders admin page
 *
 * @package Schoolbox\Admin\Import\Folder
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'adminImportFolder';


    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'getIndex', $basePath, '');
        $this->addPostRoute($router, 'postImportResults', $basePath, '');
    }


    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser());
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(RoleMapper::class),
            $this->getMapperFactory()->get(Error\Mapper::class),
            $this->getMapperFactory()->get(HomepageTemplateMapper::class),
            $this->getConfig(),
            $this->injector->get('filesystem.storageProxy'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
