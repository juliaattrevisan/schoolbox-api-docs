<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Course\DataModel\Implementation;
use Schoolbox\Learning\Course\DataModel\Specification;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Role;
use Twig_Loader_Chain;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Assessment\Term\Model as TermModel;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Error\Mapper as ErrorMapper;

class Module extends BaseModule
{
    /** @var string */
    protected $basename = 'learningAnalytics';

    /**
     * Basic routes for this module
     *
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $get = [
            // For a Course - aggregating all Classes which have imported Course
            'course'                 => "/course/{courseSpecId}",
            'courseWithTerms'        => "/course/{courseSpecId}/term/{termCode}",
            'courseWithTask'         => "/course/{courseSpecId}/task/{taskTypeId}",
            'courseWithTaskAndTerms' => "/course/{courseSpecId}/task/{taskTypeId}/term/{termCode}",

            // Per Class that uses a particular Course
            'folder'                 => "/course/{courseSpecId}/class/{folderId}",
            'folderWithTerms'        => "/course/{courseSpecId}/class/{folderId}/term/{termCode}",
            'folderWithTask'         => "/course/{courseSpecId}/class/{folderId}/task/{taskTypeId}",
            'folderWithTaskAndTerms' => "/course/{courseSpecId}/class/{folderId}/task/{taskTypeId}/term/{termCode}",
        ];

        foreach ($get as $method => $route) {
            $this->addGetRoute($router, $method, $basePath, $route);
        }
    }

    /**
     * Inject params into controller
     *
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        /* All routes that include the term will be processed, those without a term
         * will receive scalars and redirect to current term equivalent
         */
        $this->addRoutePreload(
            $matcher, 'courseWith.*Terms',
            function($params) {
                return $this->courseDependencies($params);
            }
        );

        $this->addRoutePreload(
            $matcher, 'folderWith.*Terms',
            function($params) {
                return $this->folderDependencies($params);
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this
            ->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn())
            ->addRoutePermission($authorizer, '.*', new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF)
            )
        );
    }

    /**
     * Pass params when using course view
     *
     * @param array $params
     * @var int $params['courseSpecId']
     * @var int $params['termCode']
     *
     * @return array
     * @throws MapperException
     */
    private function courseDependencies(array $params)
    {
        try {
            $courseSpecification = $this->getCourseSpecificationMapper()->findById($params['courseSpecId']);

            foreach ($courseSpecification->getUnits() as $unit) {
                // link up the other way around
                $unit->setAssessables($this->getAssessmentDataSource()->findAllByContext($unit));
            }

        } catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_DATE_FORMAT);
        }

        $term = $this->findTerm($params['termCode']);

        /* No acrobatics required
         */
        try {

            /* All the unit instances associated with the specified folder which used the same
             * $courseFolder as a source. Filter it based due dates between $from and $to
             */
            $from = $term->getStart();
            $to   = $term->getActualFinish();

            $pb = $this->getUnitImplementationMapper()->getPredicateBuilder()
                       ->equals('s.course_specification_id', $courseSpecification->getId())
                       ->notLessThan('open_date', $from->getDatabaseDate());

            if (!$to->isNull()) {
                $pb->notGreaterThan('due_date', $to->getDatabaseDate());
            }

            $unitImplementations = $this->getUnitImplementationMapper()->findByPredicates($pb);

            $everyInteraction = [];

            foreach ($unitImplementations as $unit) {
                // load up some hydrations
                $unit->setFolder($this->getFolderMapper()->findById($unit->getFolder()->getId()));

                if (isset($params['folderId']) && $unit->getFolder()->getId() != $params['folderId']) {
                    // filtering to a specific folder, and it's not this one
                    continue;
                }

                foreach ($this->getAssessmentDataSource()->findAllByContext($unit) as $activity) {
                    $this->getParticipantDataSource()->loadParticipationIntoAssessment($activity);
                    foreach ($activity->getAllParticipants() as $participant) {
                        foreach ($participant->getAllFeedbacks() as $feedback) {
                            $activity->getFeedbacks()->add($feedback);
                            $everyInteraction[] = $feedback;
                        }
                        foreach ($participant->getAllResponses() as $response) {
                            $activity->getResponses()->add($response);
                            $everyInteraction[] = $response;
                        }
                    }

                    $unit->getAssessables()->add($activity);
                }
            }

        } catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_SUBJECT_NO_TASKS);
        }

        /* Inject (this is used in twig)
         */
        $params['route'] = 'folder';

        return [
            'params'                       => $params,
            'courseSpecification'          => $courseSpecification,
            'unitImplementations'          => $unitImplementations,
            'term'                         => $term,
        ];
    }

    /**
     * Pass params when using folder view
     *
     * @param $params
     * @return array
     * @throws MapperException
     */
    private function folderDependencies($params)
    {
        $preload = $this->courseDependencies($params);

        // overlay folder selection
        try {
            $preload['selectedClassFolder'] = $this->getFolderMapper()->findById($params['folderId']);

        } catch (MapperException $e) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_SUBJECT_NO_TASKS);
        }

        return $preload;
    }


    /**
     * @param string $termCode
     * @return TermModel
     * @throws MapperException
     */
    private function findTerm($termCode)
    {
        try {
            return $this->getTermMapper()->findOneByColumn('code', $termCode);

        } catch (\Exception $e) {
            throw new MapperException('Invalid date range.');
        }
    }

    /**
     * Template locations for this module
     *
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Set up controllers
     *
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class)
        );
    }

    /* Getters / Setters
     */

    /**
     * @return object|Access
     */
    protected function getAccess()
    {
        return $this->getMapperFactory()->get(Access::class);
    }

    /**
     * @return Mapper|FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }


    /**
     * @return object|MapperProxy
     */
    protected function getAssessmentDataSource()
    {
        return $this->getInjector()->get('assessmentDataSource');
    }

    /**
     * @return Mapper|Implementation\Mapper
     */
    protected function getCourseImplementationMapper()
    {
        return $this->getMapperFactory()->get(Implementation\Mapper::class);
    }


    /**
     * @return Mapper|Specification\Mapper
     */
    protected function getCourseSpecificationMapper()
    {
        return $this->getMapperFactory()->get(Specification\Mapper::class);
    }


    /**
     * @return Mapper|Implementation\Unit\Mapper
     */
    protected function getUnitImplementationMapper()
    {
        return $this->getMapperFactory()->get(Implementation\Unit\Mapper::class);
    }

    /**
     * @return Mapper|TermMapper
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }


    /**
     * @return object|Participant\DataSource
     */
    protected function getParticipantDataSource()
    {
        return $this->getInjector()->get('participantDataSource');
    }
}
