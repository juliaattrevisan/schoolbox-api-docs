<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Grades;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\Exception\ServiceNotFound;
use Eportfolio\ShowcaseMapper;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Mapper;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Aura\Di\ContainerInterface;
use Schoolbox\Role;
use \Twig_Loader_Chain;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
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
    public function __construct(ContainerInterface $injector, $basename = '')
    {
        if('' === $basename){
            $basename = 'learningGrades';
        }
        parent::__construct($injector, $basename);
    }


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException If cannot find a specified mapper class
     * @throws NotFound If the specified user is not found
     */
    public function registerRoutes()
    {
        $permissions = new Rule\Any(
            new Rule\IsSuperuser(),
            new Rule\HasRole(Role::STAFF),
            new Rule\IsUser('userId'),
            new Rule\All(
                new Rule\IsTrue(!$this->getConfig()->get('restrict_parent_grades')),
                new Rule\IsParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'userId')
            )
        );

        $loadViewee = function ($params) {
            try {
                return ['viewee' => $this->getUserMapper()->findById($params['userId'])];
            } catch (Mapper\Exception $e) {
                throw new NotFound("No user found for id: {$params['userId']}");
            }
        };

        $loadTerm = function ($params) {
            if(null === $params['termCode']){
                return ['term' => $this->getTermMapper()->findForToday()];
            }
            try{
                return ['term' => $this->getTermMapper()->findOneByColumn('code', $params['termCode'])];
            } catch (Mapper\Exception $e){
                throw new NotFound("No term found for code: {$params['termCode']}");
            }
        };

        $loadTermAndViewee = function ($params) use($loadViewee, $loadTerm) {
            return array_merge($loadViewee($params), $loadTerm($params));
        };


        // Display list of folders with interactions for specified (or current if null) term
        $this->register('GET', '/summary/{userId:\d+}{/termCode}',        'getSummaryGrades',    $permissions, $loadTermAndViewee);

        // Convenience redirects to filtered view:
        $this->register('GET', '/{userId:\d+}/{subjectId:\d+}{/termCode}', 'getGradesForSubject', $permissions, $loadTerm);

        // Filtered routes
        $this->register('GET',  '/{userId:\d+}',                          'getGrades',           $permissions, $loadViewee);
        $this->register('POST', '/{userId:\d+}',                          'postGrades',          $permissions, $loadViewee);  //AJAX in the report
    }


    /**
     * @inheritdoc
     * @throws \Twig_Error_Loader If template directory not found
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
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
            'errorMapper'                => $this->getMapperFactory()->getLazy(ErrorMapper::class),
            'assessmentMapperProxy'      => $this->getInjector()->lazyGet('assessmentDataSource'),
            'folderMapper'               => $this->getMapperFactory()->getLazy(FolderMapper::class),
            'termMapper'                 => $this->getMapperFactory()->getLazy(TermMapper::class),
            'defaultGradeGroup'          => $this->getInjector()->get('defaultGradeGroup'),
            'showcaseMapper'             => $this->getMapperFactory()->getLazy(ShowcaseMapper::class),
            'rubricMapper'               => $this->getMapperFactory()->getLazy(RubricMapper::class),
            'quizMapper'                 => $this->getMapperFactory()->getLazy(QuizMapper::class),
            'quizAttemptMapper'          => $this->getMapperFactory()->getLazy(QuizAttemptMapper::class),
            'timetableColourMapper'      => $this->getMapperFactory()->getLazy(Timetable_ColourMapper::class),
            'courseImplementationMapper' => $this->getMapperFactory()->getLazy(CourseImplementationMapper::class),
            'courseSpecificationMapper'  => $this->getMapperFactory()->getLazy(CourseSpecificationMapper::class),
            'filterSelectionMapper'      => $this->getMapperFactory()->getLazy(FilterSelection\Mapper::class),
            'filterMapper'               => $this->getMapperFactory()->getLazy(Filter\Mapper::class),
            'filterFactoryFactory'       => $injector->lazy(function(){
                return new Filter\Filters\Assessment\FactoryFactory($this->getMapperFactory());
            }),
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
