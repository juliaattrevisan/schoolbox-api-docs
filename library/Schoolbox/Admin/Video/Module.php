<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Video;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Video\QueueMapper;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * The module for managing school terms through the admin section.
 *
 * @package Schoolbox\Admin\Menu\Side
 */
class Module extends BaseModule
{
    protected $basename = 'adminVideo';

    /**
     * @inheritdoc
     */
    public function initRoutes(string $basePath)
    {
        $routes = [
            'GET' => [
                '' => 'getIndex',
                '{/queueId}' => 'getLog',
            ],
            'POST' => [
                '/submit' => 'postSubmit',
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
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Preload required data for the various routes

        $queueMapper = $this->getQueueMapper();

        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) use ($queueMapper) {

                $returnArray = [];

                if (array_key_exists('queueId', $params)) {
                    try {
                        $returnArray['queue'] = $queueMapper->findById($params['queueId']);
                    } catch (MapperException $e) {
                    }
                }

                return $returnArray;
            }
        );
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
        return new Controller();
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @return QueueMapper
     */
    public function getQueueMapper()
    {
        return $this->getMapperFactory()->get(QueueMapper::class);
    }
}
