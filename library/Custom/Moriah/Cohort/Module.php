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
use External_UserInterface;
use Schoolbox\Assessment\Term;
use Schoolbox\Resource\Folder;
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
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var Folder\Mapper
     */
    protected $folderMapper;

    /**
     * @var Term\Mapper
     */
    protected $termMapper;

    /**
     * @var External_UserInterface
     */
    protected $externalDb;

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
     * @inheritDoc
     */
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        $this->userMapper   = UserMapper::getInstance();
        $this->folderMapper = Folder\Mapper::getInstance();
        $this->termMapper   = Term\Mapper::getInstance();
        $this->externalDb   = new \External_User();
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
                    new Rule\IsParentOfUser($this->externalDb, $this->userMapper, 'studentId')
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
                return ['student' => $this->userMapper->findById($params['studentId']),];
            }
        );
        $this->addRoutePreload(
            $matcher,
            '(?:classReport|classReportForTerm)',
            function ($params) {
                return ['class' => $this->folderMapper->findById($params['classId']),];
            }
        );
        $this->addRoutePreload(
            $matcher,
            'classReportForTerm',
            function ($params) {
                return ['term' => $this->termMapper->findOneByColumn('code', $params['termCode']),];
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
                    'folderMapper'       => $this->folderMapper,
                    'unitInstanceMapper' => $injector->lazyNew(Course\UnitInstanceMapper::class),
                    'termMapper'         => $this->termMapper,
                ]
            )
        );
    }
}
