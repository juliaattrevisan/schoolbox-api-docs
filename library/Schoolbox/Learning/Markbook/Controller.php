<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Markbook;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\MarkScheme\Completion;
use Schoolbox\Learning\MarkScheme\None;
use Schoolbox\Learning\MarkScheme\TotalMarkAbstract;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group as GradeGroup;
use Schoolbox\Assessment\Term;
use DueWork_ErrorMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment as AssessmentFilter;
use Schoolbox\Filter\Option as FilterOption;
use Schoolbox\User;
use Schoolbox\GenericCollection;

class Controller extends Base
{

    /** @var AssessmentMapperProxy */
    private $assessmentMapperProxy;

    /** @var GradeGroup\Model */
    private $defaultGradeGroup;

    /** @var Term\Mapper */
    private $termMapper;

    /**  @var DueWork_ErrorMapper */
    private $dueWorkErrorMapper;

    /** @var Access */
    private $access;

    /** @var AssessmentFilter\IdFinder */
    private $assessmentIdFinder;

    /** @var AssessmentFilter\FactoryFactory */
    private $filterFactoryFactory;


    /**
     * @param AssessmentMapperProxy           $assessmentMapperProxy
     * @param GradeGroup\Model                $defaultGradeGroup
     * @param Term\Mapper                     $termMapper
     * @param DueWork_ErrorMapper             $dueWorkErrorMapper
     * @param Access                          $access
     * @param AssessmentFilter\IdFinder       $assessmentIdFinder
     * @param AssessmentFilter\FactoryFactory $filterFactoryFactory
     */
    public function __construct(
        AssessmentMapperProxy $assessmentMapperProxy,
        GradeGroup\Model $defaultGradeGroup,
        Term\Mapper $termMapper,
        DueWork_ErrorMapper $dueWorkErrorMapper,
        Access $access,
        AssessmentFilter\IdFinder $assessmentIdFinder,
        AssessmentFilter\FactoryFactory $filterFactoryFactory
    ) {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->defaultGradeGroup     = $defaultGradeGroup;
        $this->termMapper            = $termMapper;
        $this->dueWorkErrorMapper    = $dueWorkErrorMapper;
        $this->access                = $access;
        $this->assessmentIdFinder    = $assessmentIdFinder;
        $this->filterFactoryFactory  = $filterFactoryFactory;
    }


    /**
     * Redirect to the markbook for the specified class and current term
     *
     * @param int       $folderId
     * @param User|null $viewee
     * @throws RouteNotFound If unable to generate the redirect route
     */
    public function getMarkbookForClass($folderId, User $viewee = null)
    {
        $currentTerm = $this->termMapper->findForToday();
        $this->getMarkbookForClassAndTerm($folderId, $currentTerm, $viewee);
    }

    /**
     * Redirect to the markbook for the specified class and term
     *
     * @param int        $folderId
     * @param Term\Model $term
     * @param null|User  $viewee
     * @throws RouteNotFound If unable to generate the redirect route
     */
    public function getMarkbookForClassAndTerm($folderId, Term\Model $term, User $viewee = null)
    {
        $user        = $viewee ?: $this->getUser();
        $route       = $this->getRouter()->generate('learningMarkbook.getMarkbook', ['userId' => $user->getId()]);

        $filterMapper = Filter\Mapper::getInstance();
        $selectionMapper = Filter\Selection\Mapper::getInstance();

        $selections = new GenericCollection(GenericCollection::class);
        $filters    = $filterMapper->findForEntity(\Schoolbox\Learning\Assessment\DataModel\Mapper::class);
        foreach($filters as $filter){
            $selections[$filter->getId()] = $selectionMapper->createCollection();

            if(Filter\Filters\Assessment\Folder\TeacherInvolved\Definition::class === $filter->getClass()){
                $selections[$filter->getId()][$folderId] = $this->createSelectionModel($selectionMapper, $filter, (string)$folderId);
            } elseif(Filter\Filters\Assessment\Term\Due\Definition::class === $filter->getClass()){
                $selections[$filter->getId()][$term->getId()] = $this->createSelectionModel($selectionMapper, $filter, (string)$term->getId());
            }
        }

        $codec = new Filter\Codec();
        $getArgString = $codec->convertSelectionsToQueryString($selections);

        $this->getResponse()->redirect->to("{$route}?{$getArgString}");
    }


    /**
     * Helper method to create a Filter Selection model
     *
     * @param Filter\Selection\Mapper $selectionMapper
     * @param Filter\Model            $filter
     * @param string                  $key
     * @return Filter\Selection\Model
     */
    private function createSelectionModel(Filter\Selection\Mapper $selectionMapper, Filter\Model $filter, $key)
    {
        $selection = $selectionMapper->createModel();
        $selection
            ->setFilter($filter)
            ->setUser($this->getUser())
            ->setPage(FilterOption\Model::PAGE_MARK_BOOK)
            ->setKey($key)
            ->setSelected(true);
        return $selection;
    }


    /**
     * Displays a list of marks for students in the specified class
     *
     * @api   GET /learning/markbook?f=1,2&td=3
     * @level 165
     * @param null|User $viewee Whose markbook are we looking at?
     * @throws RouteNotFound
     * @throws \InvalidArgumentException If unable to locate Filter mapper class, or required filter definition classes
     * @throws \ReflectionException      If the specified filter definition class does not exist
     * @throws ServiceNotFound           If unable to locate HTML web Output
     * @throws \DomainException          If of the filters on this page does not have its slug set
     */
    public function getMarkbook(User $viewee = null)
    {
        // If viewee not specified, then display current user's markbook.
        if (null === $viewee) {
            $viewee = $this->getUser();
        }
        $page      = FilterOption\Model::PAGE_MARK_BOOK;
        $route     = $this->getRouter()->generate('learningMarkbook.postMarkbook', ['userId' => $viewee->getId()]);
        $queryArgs = $this->getRequest()->query->getArrayCopy();

        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $viewee, $queryArgs);

        // Is a teacher looking at their own markbook, or admin looking at a teacher's markbook?
        $pageTitle = 'Markbook';
        if ($viewee->getId() !== $this->getUser()->getId()) {
            $pageTitle .= " for {$viewee->getFullName()}";
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@learningMarkbook/markbookOuter.twig', [
                'viewee'      => $viewee,
                'filterPanel' => $filterPanel,
            ])->setTab('submission')->setTitle($pageTitle)
        );
    }


    /**
     * Receives a set filter panel selections and returns a filtered markbook
     *
     * @param User $viewee
     * @todo convert this to a GET on the same route name as getMarkbook, but distinguish as being AJAX
     */
    public function postMarkbook(User $viewee)
    {
        try {
            $postFilterSelections = $this->request->post['filter_selection'] ?: [];

            /**
             * @var Filter\DataTransferObject $filtersDto
             * @var Filter\Codec              $codec
             * @var Filter\Selection\Model[][]
             */
            list($filtersDto, $codec, $selectionsPerFilter) = $this->convertRequestToFilters($postFilterSelections, true);

            // Ensure that assessments are not Assessment Specs
            $filters = array_merge($filtersDto->getByClass(), [
                AssessmentFilter\NullableContext\UnitSpec\Definition::class => [],
            ]);

            $assessmentIds = $this->assessmentIdFinder->findIdsByFilters($filters);
            $markbook      = $this->buildMarkbook($assessmentIds);

            $this->getResponse()->content->set($this->buildJsonOutput([
                'status'  => 200,
                'content' => $this->buildUnthemedHtmlOutput('@learningMarkbook/markbookInner.twig', [
                    'markbook'     => $markbook,
                    'viewee'       => $viewee,
                    'writable'     => $this->getAssessmentsWritability($markbook->getAssessments()),
                    'pendingLabel' => $this->getPendingLabel(),
                    'queryArgs'    => $codec->convertSelectionsToQueryString($selectionsPerFilter),
                ])->getAsString(),
            ]));

        } catch (Filter\BrokenFilterException $e) {
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
        } catch (\Exception $e) {
            $this->returnJsonError($e);
            // TODO - can remove this block when we abandon PHP 5 support
        } catch (\Throwable $e) {
            $this->returnJsonError($e);
        }
    }


    /**
     * @param array $request
     * @param bool  $save Should we persist these selections to the DB ?
     * @return array
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \ReflectionException If the specified class does not exist
     * @throws \DomainException If this class has not defined what entity it is filtering
     */
    private function convertRequestToFilters(array $request, $save)
    {
        $selectionMapper = Filter\Selection\Mapper::getInstance(); // TODO pull from DI
        $page            = FilterOption\Model::PAGE_MARK_BOOK;
        $keyConverters   = $this->filterFactoryFactory->buildKeyConverters($page);
        $codec           = new Filter\Codec();

        // Convert Request to Selection models
        $selectionsPerFilter = $codec->convertRequestToSelectionModels($selectionMapper, $keyConverters, $this->getUser(), $page, $request);
        if ($save) {
            $selectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);
        }

        // Convert Selection models to Filters
        $selectionTransformer = new AssessmentFilter\Transformer($keyConverters, $selectionsPerFilter);
        $filtersTODO          = $selectionTransformer->buildFiltersDto();

        return [$filtersTODO->getByClass(), $codec, $selectionsPerFilter];
    }


    /**
     * Stream a filtered markbook in CSV format
     *
     * @api   GET /learning/markbook/:userId/export?{filterQueryArgs}
     * @throws \DomainException If attempting to get the slug from a filter where it has not been set
     * @throws Filter\BrokenFilterException If a injected filter is unable to be processed
     * @throws \InvalidArgumentException If any filter keys not recognised
     * @throws \ReflectionException If a specified filter class does not exist
     */
    public function getMarkbookExport()
    {
        $filtersArray = $this->request->query->getArrayCopy();
        unset($filtersArray['page']);

        /** @var Filter\DataTransferObject $filtersDto */
        list($filtersDto) = $this->convertRequestToFilters($filtersArray, false);

        // Ensure that assessments are not Assessment Specs
        $filters = array_merge($filtersDto->getByClass(), [
            AssessmentFilter\NullableContext\UnitSpec\Definition::class => [],
        ]);

        $assessmentIds = $this->assessmentIdFinder->findIdsByFilters($filters);
        $markbook      = $this->buildMarkbook($assessmentIds);

        $csvExporter = new Export\CSV();
        $csvExporter->export($markbook, 'Markbook.csv');
        exit;
    }



    /**
     * @param array $assessmentIds
     * @return MarkBook
     */
    private function buildMarkbook(array $assessmentIds)
    {
        // Fetch assessments & participants
        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds);
        $assessments = $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments);

        // Build a markbook
        $markbook = new Markbook($assessments, $this->defaultGradeGroup, $this->getUser());
        $markbook->getAssessments()->sortBy(['dueAt', 'sequence'], ['asc', 'asc']);
        return $markbook;
    }


    /**
     * @param GenericCollection|IAssessable[] $assessments
     * @return bool[]
     */
    private function getAssessmentsWritability(GenericCollection $assessments)
    {
        /* Determine which assessments the user should be able to mark.
         *
         * Can only mark an assessment if:
         * * Assessment is/was not due in a term that is archived, and
         * * User has write+ perm to the page
         */
        /** @var bool[] $writableAssessments Indexed by assessment.id */
        $writable = [];

        $terms = $this->termMapper->findAll(['order' => ['start' => 'ASC']]);
        foreach ($assessments as $assessment) {
            $writable[$assessment->getId()] = $this->isAssessmentMarkable($this->getUser(), $assessment, $terms);
        }
        return $writable;
    }


    /**
     * @return null|string
     */
    private function getPendingLabel()
    {
        // What text should we display if student has submitted but not been marked?
        try {
            return $this->dueWorkErrorMapper->findDefaultForNewSubmissions()->getDisplayAs();
        } catch (Mapper\Exception $e) {
            // Let Twig figure it out
            return null;
        }
    }


    /**
     * Convert comma-separated strings into arrays
     *
     * @param null[]|Collection[] $filters
     * @return string
     */
    private function buildQueryArgs(array $filters)
    {
        // Convert filters to query args so export can retrieve this report
        // TODO Convert $filters from array to DTO and make this a method on that DTO. SA-3379  SA-3201
        $queryArgs = [];
        foreach ($filters as $slug => $filter) {
            if (!$filter instanceof Collection) {
                continue;
            }
            $queryArg = "$slug=";
            if (0 === $filter->count()) {
                $queryArg .= 'none';
            } else {
                $queryArg .= implode(',', $filter->keys());
            }

            $queryArgs[] = $queryArg;
        }
        return implode('&', $queryArgs);
    }


    /**
     * Returns a JSON representation of an exception suitable for AJAX HTML alert divs
     *
     * @param \Throwable $e
     */
    protected function returnJsonError($e)
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


    /**
     * Can the specified user mark the specified assessment?
     *
     * Will be false if assessment's folder is archived, or if user lacks WRITE+ perm to folder
     *
     * @param User                           $user
     * @param IAssessable                    $assessment
     * @param GenericCollection|Term\Model[] $terms
     * @return bool
     */
    private function isAssessmentMarkable(User $user, IAssessable $assessment, GenericCollection $terms)
    {
        foreach ($terms as $term) {
            if ($term->contains($assessment->getDueAt())) {
                if ($term->getArchived()) {
                    return false;
                }
                // Rely on access cache to catch repeated calls to same folder.
                return $this->access->isInstructor($user, $assessment->getContext()->getFolder());
            }
        }
        // Logically should never get to here. It means assessment is due before first term in system starts.
        return false;
    }
}
