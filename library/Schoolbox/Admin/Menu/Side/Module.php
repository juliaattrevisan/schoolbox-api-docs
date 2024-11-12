<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Menu\Side;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Permission\Access\UserAccess;

/**
 * The module for managing school terms through the admin section.
 *
 * @package Schoolbox\Admin\Menu\Side
 */
class Module extends BaseModule
{
    protected $basename = 'adminMenuSide';

    /**
     * @inheritdoc
     */
    public function initRoutes(string $basePath)
    {
        $routes = [
            'GET' => [
                '' => 'getIndex',
                '/insert' => 'getCreate',
                '/modify/{id}' => 'getModify',
            ],
            'POST' => [
                '/delete' => 'postDelete',
                '/order' => 'postOrder',
                '/order-section' => 'postSectionOrder',
                '/modify' => 'postModify',
                '/modify-section/{sectionId}/{field}' => 'postSectionModify',
            ]
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($alias, $basePath, $path);
        }
    }

    /**
     * @inheritdoc
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
                    new Rule\HasServicePermission(UserAccess::SERVICE_DESIGN, UserAccess::ACCESS_FULL)
                )
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller();
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
