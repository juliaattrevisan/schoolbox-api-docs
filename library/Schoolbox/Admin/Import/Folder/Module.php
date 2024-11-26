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
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Error;
use Schoolbox\Permission\Access\UserAccess;
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
    public function initRoutes(string $basePath)
    {
        $this->addGetRoute('getIndex', $basePath, '');
        $this->addPostRoute('postImportResults', $basePath, '');
    }


    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission(
            $authorizer,
            '.*',
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasServicePermission(UserAccess::SERVICE_CMS, UserAccess::ACCESS_FULL)
                )
            )
        );
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
