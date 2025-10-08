<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Terminology;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Schoolbox\L10n\Mapper as L10nMapper;
use Alaress\Twig\Loader\IChainLoader;

/**
 * The module for managing school terms through the admin section.
 *
 * @package Schoolbox\Admin\Terminology\Side
 */
class Module extends BaseModule
{
    /**
     * Its the base... name of this module...
     * @var string
     */
    protected $basename = 'adminTerminology';

    /**
     * @inheritdoc
     */
    public function initRoutes(string $basePath)
    {
        $routes = [
            'GET' => [
                '' => 'getIndex',
            ],
            'POST' => [
                '/update' => 'postUpdateTerms'
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
                new Rule\IsSuperuser(),
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(\Schoolbox\Config\ConfigMapper::class),
            $this->getMapperFactory()->get(L10nMapper::class),
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
