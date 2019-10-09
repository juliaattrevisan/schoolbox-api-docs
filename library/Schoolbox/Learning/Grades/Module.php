<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Learning\Grades;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Term;
use Schoolbox\Error;
use Schoolbox\Filter;
use Schoolbox\Filter\Selection as FilterSelection;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\UserMapper;

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
        $permissions = new Rule\LazyCallback(function () {
            return new Rule\All(
                new Rule\IsLoggedIn(),
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\IsUser('userId'),
                    new Rule\All(
                        new Rule\IsTrue(!$this->getConfig()->get('restrict_parent_grades')),
                        new Rule\IsParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'userId')
                    )
                )
            );
        });

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

        $loadFolder = function ($params) {
            try {
                return ['subject' => $this->getFolderMapper()->findById($params['subjectId'])];
            } catch (Mapper\Exception $e) {
                throw new NotFound("No folder found for id: {$params['subjectId']}");
            }
        };

        $loadVieweeTerm = function ($params) use($loadViewee, $loadTerm) {
            return array_merge($loadViewee($params), $loadTerm($params));
        };

        $loadVieweeTermFolder = function ($params) use($loadViewee, $loadTerm, $loadFolder) {
            return array_merge($loadViewee($params), $loadTerm($params), $loadFolder($params));
        };

        $this->register('GET', '/grades-year/{userId}/year/{year}', 'getGradesYear', $permissions, $loadViewee);

        // Overview and single class pages
        $this->register('GET', '', 'getGradesForCurrentUser', new Rule\IsLoggedIn());
        $this->register('GET', '/{userId:\d+}{/termCode}',                         'getGrades',           $permissions, $loadVieweeTerm);
        $this->register('GET', '/{userId:\d+}/{termCode}/{subjectId:\d+}',         'getGradesForSubject', $permissions, $loadVieweeTermFolder);

        // Filtered routes (todo merge GET + POST and distinguish via sync vs. ajax)
        $this->register('POST', '/{userId:\d+}/{termCode}/{subjectId:\d+}', 'postReport',          $permissions, $loadVieweeTermFolder);
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
            'termMapper'                 => $this->getMapperFactory()->getLazy(Term\Mapper::class),
            'folderMapper'               => $this->getMapperFactory()->getLazy(Folder\Mapper::class),
            'defaultGradeGroup'          => $this->getInjector()->get('defaultGradeGroup'),
            'rubricMapper'               => $this->getMapperFactory()->getLazy(RubricDataSource::class),
            'access'                     => $this->getMapperFactory()->getLazy(Access::class),
            'filterFactoryFactory'       => $injector->lazyGet('filters.factoryFactory'),
            'filterSelectionMapper'      => $this->getMapperFactory()->getLazy(FilterSelection\Mapper::class),
            'assessmentIdFinder'         => $injector->lazyGet('filters.assessmentIdFinder'),
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
     * @return Mapper|Folder\Mapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }

    /**
     * @return Mapper|Term\Mapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(Term\Mapper::class);
    }

    /**
     * @return Mapper|Error\Mapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(Error\Mapper::class);
    }
}
