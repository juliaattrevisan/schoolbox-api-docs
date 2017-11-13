<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\Exception\ServiceNotFound;
use Eportfolio\ShowcaseMapper;
use Schoolbox\Application\Exception as ApplicationException;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Mapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Role;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use \Timetable_ColourMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Filter\Selection as FilterSelection;
use Schoolbox\Filter;
use \Schoolbox\Learning\Assessment\DataModel as Assessment;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learning';

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        /** @var string[][] $routes */
        $routes = [
            'GET' => [
                '/pending'                                => 'getPendingAssessments',        // (no preload)
                '/pending/{userId}'                       => 'getPendingAssessmentsForUser', // viewee
                '/classes{/termCode}'                     => 'getClasses',                   // (no preload)
            ],
            'POST' => [
                '/pending/{userId}'                       => 'postPendingAssessmentsForUser', // viewee
            ]
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
     * @throws \InvalidArgumentException
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '(?:getPendingAssessments|getClasses)', new Rule\IsLoggedIn());

        // Either a SU or staff viewing their own assessments
        $this->addRoutePermission(
                $authorizer,
                '(?:getPendingAssessmentsForUser|postPendingAssessmentsForUser)',
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\All(
                        new Rule\IsUser('userId'),
                        new Rule\HasRole(Role::STAFF)
                    )
                )
            );
    }

    /**
     * @inheritdoc
     * @throws ApplicationException\Exception If Invalid user id specified
     * @throws \InvalidArgumentException If mapper definition not found
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '(?:getPendingAssessmentsForUser|postPendingAssessmentsForUser)',
            function () {
                return [
                    'filterFactoryFactory' => new Filter\Filters\Assessment\FactoryFactory($this->getMapperFactory()),
                ];
            }
        );

        $this->addRoutePreload(
            $matcher,
            '(?:getPendingAssessmentsForUser|postPendingAssessmentsForUser|getClasses)',
            function ($params) {
                if (array_key_exists('userId', $params)) {
                    try {
                        $args['viewee'] = $this->getUserMapper()->findById($params['userId']);
                    } catch (MapperException $e) {
                        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
                    }
                }

                try {
                    if ($params['termCode']) {
                        $args['term'] = $this->getTermMapper()->findOneByColumn('code', $params['termCode']);
                    } else {
                        $args['term'] = $this->getTermMapper()->findForToday();
                    }

                } catch (MapperException $e) {
                    /* If nonexistent term is specified, 404
                     */
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
                }

                if (array_key_exists('subjectId', $params)) {
                    try {
                        $args['subject'] = $this->getFolderMapper()->findById($params['subjectId']);
                    } catch (MapperException $e) {

                        /* If nonexistent subject is specified, 404
                         */
                        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
                    }
                }

                return $args;
            }
        );
    }


    /**
     * @inheritdoc
     * @throws \Twig_Error_Loader If template directory not found
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * {@inheritdoc}
     * @throws \InvalidArgumentException If mapper class not found
     * @throws ServiceNotFound If cannot fetch service from DI
     */
    protected function preInitControllerDependencies(ContainerInterface $injector)
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'assessmentMapperProxy'      => $this->getInjector()->lazyGet('assessmentDataSource'),
            'folderMapper'               => $this->getMapperFactory()->getLazy(FolderMapper::class),
            'termMapper'                 => $this->getMapperFactory()->getLazy(TermMapper::class),
            'timetableColourMapper'      => $this->getMapperFactory()->getLazy(Timetable_ColourMapper::class),
            'courseImplementationMapper' => $this->getMapperFactory()->getLazy(CourseImplementationMapper::class),
            'courseSpecificationMapper'  => $this->getMapperFactory()->getLazy(CourseSpecificationMapper::class),
            'filterSelectionMapper'      => $this->getMapperFactory()->getLazy(FilterSelection\Mapper::class),
            'assessmentIdFinder'         => $injector->lazy(function(){
                /** @var Filter\Mapper $filterMapper */
                $filterMapper = $this->getMapperFactory()->get(Filter\Mapper::class);
                return Filter\Filters\Assessment\IdFinder::getInstance(
                    $filterMapper, $this->getMapperFactory()->get(Assessment\Mapper::class)
                );
            }),
        ];
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return Mapper|FolderMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }

    /**
     * @return Mapper|TermMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }

    /**
     * @return Mapper|ErrorMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
