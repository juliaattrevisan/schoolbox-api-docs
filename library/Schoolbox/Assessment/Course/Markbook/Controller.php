<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Markbook;

use Schoolbox\Assessment\Course\Task\Completion;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Alaress\Web\Controller as WebController;
use Schoolbox\Filter;
use Schoolbox\Learning\MarkScheme\LetterGrade;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group as GradeGroup;
use Throwable;

class Controller extends WebController\Base
{

    /** @var Filter\Filters\Assessment\FactoryFactory */
    private $filterFactoryFactory;

    /** @var Filter\Mapper */
    private $filterMapper;

    /** @var Filter\Selection\Mapper */
    private $filterSelectionMapper;

    /** @var Filter\Filters\Assessment\IdFinder */
    private $assessmentIdFinder;

    /** @var Assessment\MapperProxy */
    private $assessmentMapperProxy;

    /** @var GradeGroup\Model */
    private $defaultGradeGroup;


    /**
     * @param Filter\Filters\Assessment\FactoryFactory $filterFactoryFactory
     * @param Filter\Mapper                            $filterMapper
     * @param Filter\Selection\Mapper                  $filterSelectionMapper
     * @param Filter\Filters\Assessment\IdFinder       $assessmentIdFinder
     * @param Assessment\MapperProxy                   $assessmentMapperProxy
     * @param GradeGroup\Model                         $defaultGradeGroup
     */
    public function __construct(
        Filter\Filters\Assessment\FactoryFactory $filterFactoryFactory,
        Filter\Mapper $filterMapper,
        Filter\Selection\Mapper $filterSelectionMapper,
        Filter\Filters\Assessment\IdFinder $assessmentIdFinder,
        Assessment\MapperProxy $assessmentMapperProxy,
        GradeGroup\Model $defaultGradeGroup
    ) {
        $this->filterFactoryFactory  = $filterFactoryFactory;
        $this->filterMapper          = $filterMapper;
        $this->filterSelectionMapper = $filterSelectionMapper;
        $this->assessmentIdFinder    = $assessmentIdFinder;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->defaultGradeGroup     = $defaultGradeGroup;
    }


    /**
     * Redirects to the course markbook, ensuring all units in the course are visible, with the specified unit pre-selected
     *
     * @level 18.0
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
        /** @var Filter\Model[] $filters */
        $filters      = $this->filterMapper->findByClass([
            Filter\Filters\Assessment\NullableContext\UnitImportSpec\Definition::class,
            Filter\Filters\Assessment\Folder\CourseImports\Definition::class,
        ])->indexBy('class');
        $unitFilter   = $filters[Filter\Filters\Assessment\NullableContext\UnitImportSpec\Definition::class];
        $folderFilter = $filters[Filter\Filters\Assessment\Folder\CourseImports\Definition::class];

        $selections   = new GenericCollection(GenericCollection::class);

        // Unit Filter options
        $selections[$unitFilter->getId()] = $this->filterSelectionMapper->createCollection();
        foreach ($courseSpec->getUnits() as $courseUnitSpec) {
            $courseUnitSpecId                                = $courseUnitSpec->getId();
            $selected                                        = $unitSpecId && $unitSpecId === (int)$courseUnitSpec->getId();
            $selections[$unitFilter->getId()][$courseUnitSpecId] = $this->createSelectionModel($unitFilter, (string)$courseUnitSpecId, $selected);
        }

        // Folder Filter options
        if($folderId){
            $selections[$folderFilter->getId()] = $this->filterSelectionMapper->createCollection();
            $selections[$folderFilter->getId()][$folderId] = $this->createSelectionModel($folderFilter, (string)$folderId, true);
        }

        $getArgString = $this->filterFactoryFactory->buildCodec()->convertSelectionsToQueryString($selections);
        $route        = $this->getRouter()->generate('courseMarkbook.getMarkbook', ['courseSpecId' => $courseSpec->getId()]);

        $this->getResponse()->redirect->to("{$route}?{$getArgString}");
    }


    /**
     * Renders the outer part of the course markbook page with a filter panel
     *
     * @level 18.0
     * @param CourseSpec\Model $courseSpec
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Di\Exception\ServiceNotFound
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
        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $this->getUser(), $queryArgs);

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@courseMarkbook/courseMarkbookOuter.twig', [
                'courseSpec'  => $courseSpec,
                'viewee'      => $this->getUser(),
                'filterPanel' => $filterPanel,
            ])->setTab('submission')->setTitle("Course Markbook: {$courseSpec->getTitle()}")
        );
    }


    /**
     * Loads a filtered report into the Course Markbook page
     *
     * @param CourseSpec\Model $courseSpec
     * @level 18.0
     * @todo  Condense this and the GET route into a single route and distinguish via SYNC vs AJAX
     */
    public function postMarkbook(CourseSpec\Model $courseSpec): void
    {
        try {
            $request = $this->request->post['filter_selection'] ?: [];

            /**
             * @var null[]|Collection[]        $filters
             * @var Filter\Codec               $codec
             * @var Filter\Selection\Model[][] $selectionsPerFilter
             */
            // TODO add some intelligence around which filter settings to save (eg AssessmentType but not units)
            [$filters, $codec, $selectionsPerFilter] = $this->convertRequestToFilters($request, false);

            $assessmentIds  = $this->assessmentIdFinder->findIdsByFilters($filters);
            $courseMarkbook = $this->buildCourseMarkbook($courseSpec, $assessmentIds);

            $this->getResponse()->content->set($this->buildJsonOutput([
                'status'  => 200,
                'content' => $this->buildUnthemedHtmlOutput('@courseMarkbook/courseMarkbookInner.twig', [
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
     * @param array $request  Indexed by filter.id, option.key
     * @param bool  $saveToDB Should we persist these selections to the DB ?
     * @return array
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \ReflectionException If the specified class does not exist
     * @throws \DomainException If this class has not defined what entity it is filtering
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     * @throws \UnexpectedValueException
     */
    private function convertRequestToFilters(array $request, $saveToDB): array
    {
        $page            = Filter\Page\Model::PAGE_COURSE_MARK_BOOK;
        $codec           = $this->filterFactoryFactory->buildCodec();

        // Convert Request to Selection models
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);
        if ($saveToDB) {
            $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);
        }

        $filters = $this->filterFactoryFactory->convertSelectionsToFilters($selectionsPerFilter, $this->getUser(), $page);

        // Ensure that assessments are not Assessment Specs
        $filters = array_merge($filters, [
            Filter\Filters\Assessment\NullableContext\UnitSpec\Definition::class => [],
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
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments);

        // Build a course markbook
        return new CourseMarkbook(
            $courseSpec,
            $assessments,
            $this->getUser(),
            new LetterGrade($this->defaultGradeGroup)
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
