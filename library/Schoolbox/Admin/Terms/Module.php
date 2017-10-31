<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\Terms;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Alaress\Twig\Loader\IChainLoader;

/**
 * The module for managing school terms through the admin section.
 *
 * @package Schoolbox\Admin\Terms
 */
class Module extends BaseModule
{
    protected $basename = 'adminTerms';

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'getIndex', $basePath, "");
        $this->addPostRoute($router, 'postCreateTerm', $basePath, "");
        $this->addPostRoute($router, 'postUpdateTerm', $basePath, "/{id}/update");
        $this->addPostRoute($router, 'postDeleteTerm', $basePath, "/{id}/delete");
        $this->addPostRoute($router, 'postArchiveTerm', $basePath, "/{id}/archive");
        $this->addPostRoute($router, 'postRestoreTerm', $basePath, "/{id}/restore");
    }

    /**
     * @inheritdoc
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {

        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission($authorizer, '.*', new Rule\IsSuperuser())
        ;
    }

    /**
     * @inheritdoc
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this
            ->addRoutePreload($matcher, '.*', function($params) {
                if (array_key_exists('id', $params)) {
                    return ['term' => $this->getTermMapper()->findById($params['id']),];
                }
                return [];
            })
        ;
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(
                Controller::class,
                [
                    'termMapper' => $this->getMapperFactory()->getLazy(TermMapper::class),
                ]
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /* Getters / Setters
     */

    /**
     * @return TermMapper
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }
}
