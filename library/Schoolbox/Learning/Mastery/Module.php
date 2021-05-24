<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Mastery;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningMastery';

    /**
     * @param Router $router
     * @param string $basePath
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [
                '/{classId}' => 'getMastery',
                '/{classId}/term/{termCode}' => 'getMasteryForTerm'
            ],
            'POST' => []
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Preload required data for the various routes

        $folderMapper = $this->getFolderMapper();
        $termMapper = $this->getTermMapper();

        $this->addRoutePreload(
            $matcher,
            '.*',
            function ($params) use ($folderMapper, $termMapper) {

                $returnArray = [];

                // Load student record

                if (array_key_exists('classId', $params)) {
                    $returnArray['class'] = $folderMapper->findById($params['classId']);
                }

                if (array_key_exists('termCode', $params)) {
                    $returnArray['term'] = $termMapper->findOneByColumn('code', $params['termCode']);
                }

                return $returnArray;
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'errorMapper'                => $this->getMapperFactory()->getLazy(ErrorMapper::class),
                'assessmentMapperProxy'      => $injector->lazyGet('assessmentDataSource'),
                'defaultGradeGroup'          => $this->getInjector()->get('defaultGradeGroup'),
                'termMapper'                 => $this->getMapperFactory()->getLazy(TermMapper::class),
            ])
        );
    }

    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission(
            $authorizer,
            ".*",
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\HasRole(\Schoolbox\Role::STAFF)
            )
        );
    }


    /* Getters / Setters
     */

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }

    /**
     * @return TermMapper
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
