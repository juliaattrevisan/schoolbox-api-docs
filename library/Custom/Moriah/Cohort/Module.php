<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Moriah\Cohort;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Twig_Loader_Chain;

/**
 * Module definition for Moriah's custom cohort report.
 *
 * @package Custom\Moriah\Cohort
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'customMoriahCohort';

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'classList', $basePath, '/student/{studentId}');
        $this->addGetRoute($router, 'classReport', $basePath, '/student/{studentId}/class/{classId}');
        $this->addGetRoute($router, 'classReportForTerm', $basePath, '/student/{studentId}/class/{classId}/term/{termCode}');
    }

    /**
     * Only staff and a child's parents may access a cohort report.
     *
     * Students may NOT access their own cohort report.
     *
     * @inheritdoc
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission(
            $authorizer,
            '(?:classList|classReport|classReportForTerm)',
            new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\IsParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'studentId')
                )
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '(?:classList|classReport|classReportForTerm)',
            function ($params) {
                return ['student' => $this->getUserMapper()->findById($params['studentId']),];
            }
        );
        $this->addRoutePreload(
            $matcher,
            '(?:classReport|classReportForTerm)',
            function ($params) {
                return ['class' => $this->getFolderMapper()->findById($params['classId']),];
            }
        );
        $this->addRoutePreload(
            $matcher,
            'classReportForTerm',
            function ($params) {
                return ['term' => $this->getTermMapper()->findOneByColumn('code', $params['termCode']),];
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
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
                    'folderMapper'       => $this->getMapperFactory()->getLazy(FolderMapper::class),
                    'unitInstanceMapper' => $this->getMapperFactory()->getLazy(UnitInstanceMapper::class),
                    'termMapper'         => $this->getMapperFactory()->getLazy(TermMapper::class),
                ]
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
}
