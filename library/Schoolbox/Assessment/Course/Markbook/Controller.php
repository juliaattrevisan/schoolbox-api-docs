<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Markbook;

use Alaress\Web\Controller as WebController;
use Schoolbox\Assessment\Course\Markbook\Export\CSV;
use Schoolbox\Assessment\Course\Task\Completion;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Throwable;

use function array_combine;
use function func_get_args;

class Controller extends WebController\Base
{
    /** @var Filter\IFactoryFactory */
    private $filterFactoryFactory;

    /** @var Filter\Mapper */
    private $filterMapper;

    /** @var Filter\Selection\Mapper */
    private $filterSelectionMapper;

    /** @var Filter\Filters\Assessment\IdFinder */
    private $assessmentIdFinder;

    /** @var Assessment\MapperProxy */
    private $assessmentMapperProxy;

    /** @var CourseImpl\Mapper */
    private $courseImplMapper;

    /** @var UnitImpl\Mapper */
    private $unitImplMapper;


    /**
     * @param Filter\IFactoryFactory             $filterFactoryFactory
     * @param Filter\Mapper                      $filterMapper
     * @param Filter\Selection\Mapper            $filterSelectionMapper
     * @param Filter\Filters\Assessment\IdFinder $assessmentIdFinder
     * @param Assessment\MapperProxy             $assessmentMapperProxy
     * @param CourseImpl\Mapper                  $courseImplMapper
     * @param UnitImpl\Mapper                    $unitImplMapper
     */
    public function __construct(
        Filter\IFactoryFactory $filterFactoryFactory,
        Filter\Mapper $filterMapper,
        Filter\Selection\Mapper $filterSelectionMapper,
        Filter\Filters\Assessment\IdFinder $assessmentIdFinder,
        Assessment\MapperProxy $assessmentMapperProxy,
        CourseImpl\Mapper $courseImplMapper,
        UnitImpl\Mapper $unitImplMapper
    ) {
        $this->filterFactoryFactory  = $filterFactoryFactory;
        $this->filterMapper          = $filterMapper;
        $this->filterSelectionMapper = $filterSelectionMapper;
        $this->assessmentIdFinder    = $assessmentIdFinder;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->courseImplMapper      = $courseImplMapper;
        $this->unitImplMapper        = $unitImplMapper;
    }


    /**
     * Redirects to the course markbook with the specified unit and folder pre-selected
     *
     * It is possible the specified unitSpec no longer belongs to the specified courseSpec.
     * It may have been deleted or moved to a different course. This is fine, ultimately
     * the assessments imported from the unitSpec belong to the courseSpec that was linked
     * at the time of import.
     *
     * @since 18.0
     * @param CourseSpec\Model $courseSpec With all units hydrated
     * @param int              $unitSpecId 0 => ignore
     * @param int              $folderId   0 => ignore
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \InvalidArgumentException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function getRedirect(CourseSpec\Model $courseSpec, int $unitSpecId, int $folderId): void
    {
        $getArgString = '';
        if ($unitSpecId || $folderId) {
            $classes = [];
            if ($unitSpecId) {
                $classes[] = Filter\Filters\Assessment\NullableContext\UnitImportSpec\Definition::class;
            }
            if ($folderId) {
                $classes[] = Filter\Filters\Assessment\Folder\CourseImports\Definition::class;
            }
            $filters = $this->filterMapper->findByClass($classes);
            $filters->indexBy('class');

            $selections = new GenericCollection(GenericCollection::class);

            // Unit Filter options
            if ($unitSpecId) {
                $unitFilter                                    = $filters[Filter\Filters\Assessment\NullableContext\UnitImportSpec\Definition::class];
                $selections[$unitFilter->getId()]              = $this->filterSelectionMapper->createCollection();
                $selections[$unitFilter->getId()][$unitSpecId] = $this->createSelectionModel($unitFilter, (string)$unitSpecId, true);
            }

            // Folder Filter options
            if ($folderId) {
                $folderFilter                                  = $filters[Filter\Filters\Assessment\Folder\CourseImports\Definition::class];
                $selections[$folderFilter->getId()]            = $this->filterSelectionMapper->createCollection();
                $selections[$folderFilter->getId()][$folderId] = $this->createSelectionModel($folderFilter, (string)$folderId, true);
            }
            $getArgString = '?' . (new Codec($this->filterMapper, $this->filterSelectionMapper))->convertSelectionsToQueryString($selections);
        }

        $route = $this->getRouter()->generate('courseMarkbook.getMarkbook', ['courseSpecId' => $courseSpec->getId()]);

        $this->getResponse()->redirect->to("{$route}{$getArgString}");
    }


    /**
     * Renders the outer part of the course markbook page with a filter panel
     *
     * @since 18.0
     * @param CourseSpec\Model $courseSpec
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Psr\Container\NotFoundExceptionInterface|\Psr\Container\ContainerExceptionInterface
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \ReflectionException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function getMarkbook(CourseSpec\Model $courseSpec): void
    {
        $queryArgs = $this->getRequest()->query->getArrayCopy();
        unset($queryArgs['page']);

        $page        = Filter\Page\Model::PAGE_COURSE_MARK_BOOK;
        $route       = $this->getRouter()->generate('courseMarkbook.postMarkbook', ['courseSpecId' => $courseSpec->getId()]);
        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $this->getUser(), func_get_args(), $queryArgs);

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@courseMarkbook/courseMarkbookOuter.twig', [
                'courseSpec'  => $courseSpec,
                'viewee'      => $this->getUser(),
                'filterPanel' => $filterPanel,
            ])->setTab('submission')->setTitle("Course Markbook: {$courseSpec->getTitle()}")
        );
    }

    /**
     * Stream a filtered markbook in CSV format
     *
     * @api   GET /learning/courseMarkbook/export/:courseSpecId?{filterQueryArgs}
     * @param CourseSpec\Model $courseSpec
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getMarkbookExport(CourseSpec\Model $courseSpec): void
    {
        // This report may take a while
        $this->extendExecutionTime();

        $queryArgs = $this->getRequest()->query->getArrayCopy();
        [$filters, $codec, $selectionsPerFilter] = $this->convertRequestToFilters($courseSpec, $queryArgs, false);

        $assessmentIds  = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());
        $courseMarkbook = $this->buildCourseMarkbook($courseSpec, $assessmentIds);

        $csvExporter = new CSV((bool)$this->getConfigLoader()->get('view_rank'));
        $csvExporter->export($courseMarkbook, $courseSpec->getTitle() . ' - Markbook.csv');
        exit;
    }

    /**
     * Loads a filtered report into the Course Markbook page
     *
     * @param CourseSpec\Model $courseSpec
     * @since 18.0
     * @todo  Condense this and the GET route into a single route and distinguish via SYNC vs AJAX
     */
    public function postMarkbook(CourseSpec\Model $courseSpec): void
    {
        // This report may take a while
        $this->extendExecutionTime();

        try {
            $request = $this->request->post['filter_selection'] ?: [];

            /**
             * @var null[]|Collection[]        $filters
             * @var Filter\Codec               $codec
             * @var Filter\Selection\Model[][] $selectionsPerFilter
             */
            // TODO add some intelligence around which filter settings to save (eg AssessmentType but not units)
            [$filters, $codec, $selectionsPerFilter] = $this->convertRequestToFilters($courseSpec, $request, true);

            $assessmentIds  = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());
            $courseMarkbook = $this->buildCourseMarkbook($courseSpec, $assessmentIds);

            /**
             * need to get an list of the students by id, with their ranking
             */
            $rank = $courseMarkbook->getRanking();

            $this->getResponse()->content->set($this->buildJsonOutput([
                'status'  => 200,
                'content' => $this->buildUnthemedHtmlOutput('@courseMarkbook/courseMarkbookInner.twig', [
                    'rank'                    => $rank,
                    'rankEnabled'             => (bool)$this->getConfigLoader()->get('view_rank'),
                    'courseSpec'              => $courseSpec,
                    'collapsedAssessments'    => $courseMarkbook->getCollapsedAssessments(),
                    'unitSpecs'               => $courseMarkbook->getUnitSpecs(),
                    'courseImpls'             => $courseMarkbook->getCourseImpls(),
                    'courseImportAssessments' => $courseMarkbook->getCourseImportAssessments(),
                    'courseImportStudents'    => $courseMarkbook->getCourseImportStudents(),
                    'assessmentSpecMarks'     => $courseMarkbook->getAssessmentSpecAverages(),
                    'assessmentImplMarks'     => $courseMarkbook->getAssessmentImplAverages(),
                    'studentMarks'            => $courseMarkbook->getStudentMarks(),
                    'studentAverageMarks'     => $courseMarkbook->getStudentAverageMarks(),
                    'studentComments'         => $courseMarkbook->getStudentComments(),
                    'taskProgress'            => $courseMarkbook->getTaskProgress(),
                    'taskProgressPending'     => Completion::STATUS_SELF_ASSESSED_COMPLETE,
                    'taskProgressComplete'    => Completion::STATUS_TEACHER_ASSESSED_COMPLETE,
                    'queryArgs'               => $codec->convertSelectionsToQueryString($selectionsPerFilter),
                ])->getAsString(),
            ]));
        } catch (Filter\BrokenFilterException $e) {
            $this->captureException($e);
            $data = [
                'status'  => 500,
                'message' => "Sorry, the {$e->getFilterName()} filter appears to be broken. Please deselect all options and try again",
            ];
            if ($this->getConfigLoader()->get('debug_mode')) {
                $data = array_merge($data, [
                    'code'    => $e->getCode(),
                    'message' => $e->getMessage(),
                    'line'    => $e->getLine(),
                    'file'    => $e->getFile(),
                ]);
            }
            $this->getResponse()->content->set($this->buildJsonOutput($data));
        } catch (Throwable $e) {
            $this->captureException($e);
            $this->returnJsonError($e);
        }
    }


    /**
     * @param CourseSpec\Model $courseSpec
     * @param array            $request  Indexed by filter.id, option.key
     * @param bool             $saveToDB Should we persist these selections to the DB ?
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException If this class has not defined what entity it is filtering
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    private function convertRequestToFilters(CourseSpec\Model $courseSpec, array $request, bool $saveToDB): array
    {
        $page            = Filter\Page\Model::PAGE_COURSE_MARK_BOOK;
        $codec           = new Codec($this->filterMapper, $this->filterSelectionMapper);

        // Convert Request to Selection models
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);
        if ($saveToDB) {
            $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);
        }

        $filters = $this->filterFactoryFactory->convertSelectionsToFilters($selectionsPerFilter, $this->getUser(), $page);

        // Ensure that assessments are:
        // * not Assessment Specs
        // * Are inside unitImpls that belong to courseImpls that were imported from the courseSpec
        $courseImpls = $this->courseImplMapper->findAllByCourseSpecificationId($courseSpec->getId());
        if ($courseImpls->count()) {
            $pb          = $this->unitImplMapper->getPredicateBuilder()->in('course_implementation_id', $courseImpls->getPropertyValues('id'));
            $unitImplIds = $this->unitImplMapper->findIdsByPredicates($pb);
        } else {
            $unitImplIds = [];
        }
        $filters = array_merge($filters, [
            Filter\Filters\Assessment\NullableContext\UnitSpec\Definition::class => [],
            Filter\Filters\Assessment\NullableContext\UnitImpl\Definition::class => array_combine($unitImplIds, $unitImplIds),
        ]);

        return [$filters, $codec, $selectionsPerFilter];
    }


    /**
     * @param CourseSpec\Model $courseSpec
     * @param array            $assessmentIds
     * @return CourseMarkbook
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \Schoolbox\Mapper\DefinitionException
     * @throws \UnexpectedValueException
     */
    private function buildCourseMarkbook(CourseSpec\Model $courseSpec, array $assessmentIds): CourseMarkbook
    {
        // Fetch assessments & participants
        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds)->indexBy('id');
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments, null, true);

        // Build a course markbook
        return new CourseMarkbook(
            $courseSpec,
            $assessments,
            $this->getUser(),
            $this->getConfigLoader()
        );
    }


    /**
     * Helper method to create a Filter Selection model
     *
     * @param Filter\Model $filter
     * @param string       $key
     * @param bool         $selected
     * @return Filter\Selection\Model
     * @throws \Schoolbox\Mapper\Exception
     */
    private function createSelectionModel(Filter\Model $filter, string $key, bool $selected): Filter\Selection\Model
    {
        return $this->filterSelectionMapper
            ->createModel()
            ->setFilter($filter)
            ->setUser($this->getUser())
            ->setPage(Filter\Page\Model::PAGE_COURSE_MARK_BOOK)
            ->setKey($key)
            ->setSelected($selected);
    }

    /**
     * Returns a JSON representation of an exception suitable for AJAX HTML alert divs
     *
     * @param Throwable $e
     */
    private function returnJsonError(Throwable $e): void
    {
        $data = [
            'status'  => 500,
            'message' => 'Sorry, an error occurred during your request and has been logged. Please try deselecting filter options',
        ];
        if ($this->getConfigLoader()->get('debug_mode')) {
            $data = array_merge($data, [
                'code'    => $e->getCode(),
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile(),
            ]);
        }
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }
}
