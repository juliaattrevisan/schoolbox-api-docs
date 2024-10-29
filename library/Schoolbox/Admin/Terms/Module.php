<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\Terms;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Alaress\Web\Controller\Base;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\YearLevel\Mapper as YearLevelMapper;

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
    public function initRoutes(string $basePath)
    {
        $this->addGetRoute('getIndex', $basePath, "");
        $this->addPostRoute('postCreateTerm', $basePath, "");
        $this->addPostRoute('postUpdateTerm', $basePath, "/{id}/update");
        $this->addPostRoute('postDeleteTerm', $basePath, "/{id}/delete");
        $this->addPostRoute('postArchiveTerm', $basePath, "/{id}/archive");
        $this->addPostRoute('postRestoreTerm', $basePath, "/{id}/restore");
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
                new Rule\Any(
                    new Rule\IsLoggedIn(),
                    new Rule\HasServicePermission(UserAccess::SERVICE_LMS, UserAccess::ACCESS_FULL)
                )
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this
            ->addRoutePreload($matcher, '.*', function ($params) {
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
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(TermMapper::class),
            $this->getMapperFactory()->get(YearLevelMapper::class),
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
     * @return TermMapper
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }
}
