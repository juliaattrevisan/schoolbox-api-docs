<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Markbook;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Filter\Codec;
use function func_get_args;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group as GradeGroup;
use Schoolbox\Assessment\Term;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Filter;
use Schoolbox\User;
use Schoolbox\GenericCollection;
use Alaress\Database\Predicate\InvalidColumnException;

class Controller extends Base
{

    /** @var AssessmentMapperProxy */
    private $assessmentMapperProxy;

    /** @var GradeGroup\Model */
    private $defaultGradeGroup;

    /** @var Term\Mapper */
    private $termMapper;

    /**  @var StateMapper */
    private $dueWorkStateMapper;

    /** @var Access */
    private $access;

    /** @var Filter\Filters\Assessment\IdFinder */
    private $assessmentIdFinder;

    /** @var Filter\IFactoryFactory */
    private $filterFactoryFactory;


    /**
     * @param AssessmentMapperProxy              $assessmentMapperProxy
     * @param GradeGroup\Model                   $defaultGradeGroup
     * @param Term\Mapper                        $termMapper
     * @param StateMapper                        $dueWorkStateMapper
     * @param Access                             $access
     * @param Filter\Filters\Assessment\IdFinder $assessmentIdFinder
     * @param Filter\IFactoryFactory             $filterFactoryFactory
     */
    public function __construct(
        AssessmentMapperProxy $assessmentMapperProxy,
        GradeGroup\Model $defaultGradeGroup,
        Term\Mapper $termMapper,
        StateMapper $dueWorkStateMapper,
        Access $access,
        Filter\Filters\Assessment\IdFinder $assessmentIdFinder,
        Filter\IFactoryFactory $filterFactoryFactory
    ) {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->defaultGradeGroup     = $defaultGradeGroup;
        $this->termMapper            = $termMapper;
        $this->dueWorkStateMapper    = $dueWorkStateMapper;
        $this->access                = $access;
        $this->assessmentIdFinder    = $assessmentIdFinder;
        $this->filterFactoryFactory  = $filterFactoryFactory;
    }


    /**
     * Redirect to the markbook for the specified class
     *
     * @param int       $folderId
     * @param User|null $viewee
     * @throws RouteNotFound If unable to generate the redirect route
     */
    public function getMarkbookForClass(int $folderId, User $viewee = null)
    {
        $this->buildMarkbookRedirect($folderId, null, $viewee ?: $this->getUser());
    }


    /**
     * Redirect to the markbook for the specified class and filter to a term
     *
     * @param int        $folderId
     * @param Term\Model $term
     * @param null|User  $viewee
     * @throws RouteNotFound If unable to generate the redirect route
     */
    public function getMarkbookForClassAndTerm(int $folderId, Term\Model $term, User $viewee = null)
    {
        $this->buildMarkbookRedirect($folderId, $term, $viewee ?: $this->getUser());
    }


    /**
     * @param int             $folderId
     * @param null|Term\Model $term
     * @param User            $viewee
     * @throws RouteNotFound If unable to generate the redirect route
     */
    private function buildMarkbookRedirect(int $folderId, ?Term\Model $term, User $viewee)
    {
        $route = $this->getRouter()->generate('learningMarkbook.getMarkbook', ['userId' => $viewee->getId()]);

        $filterMapper    = Filter\Mapper::getInstance();
        $selectionMapper = Filter\Selection\Mapper::getInstance();

        $selections = new GenericCollection(GenericCollection::class);
        $filters    = $filterMapper->findForEntity(\Schoolbox\Learning\Assessment\DataModel\Mapper::class);
        foreach ($filters as $filter) {
            $selections[$filter->getId()] = $selectionMapper->createCollection();

            if (Filter\Filters\Assessment\Folder\TeacherInvolved\Definition::class === $filter->getClass()) {
                $selections[$filter->getId()][$folderId] = $this->createSelectionModel($selectionMapper, $filter, (string)$folderId);
            } elseif ($term && Filter\Filters\Assessment\Term\Due\Definition::class === $filter->getClass()) {
                $selections[$filter->getId()][$term->getId()] = $this->createSelectionModel($selectionMapper, $filter, (string)$term->getId());
            }
        }

        $codec        = new Codec(Filter\Mapper::getInstance(), Filter\Selection\Mapper::getInstance());
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
            ->setPage(Filter\Page\Model::PAGE_CLASS_MARK_BOOK)
            ->setKey($key)
            ->setSelected(true);
        return $selection;
    }


    /**
     * Displays a list of marks for students in the specified class
     *
     * @api   GET /learning/markbook?f=1,2&td=3
     * @since 165
     * @param null|User $viewee Whose markbook are we looking at?
     * @throws RouteNotFound
     * @throws \InvalidArgumentException             If unable to locate Filter mapper class, or required filter definition classes
     * @throws \ReflectionException                  If the specified filter definition class does not exist
     * @throws ServiceNotFound                       If unable to locate HTML web Output
     * @throws \DomainException                      If of the filters on this page does not have its slug set
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception           If an alias is incorrect
     * @throws InvalidColumnException                If an invalid column is passed to PB
     * @throws \UnderflowException                   If empty IN arg passed to PB
     * @throws \UnexpectedValueException             If wrong item type inserted in a generic collection
     */
    public function getMarkbook(User $viewee = null): void
    {
        // If viewee not specified, then display current user's markbook.
        if (null === $viewee) {
            $viewee = $this->getUser();
        }
        $page      = Filter\Page\Model::PAGE_CLASS_MARK_BOOK;
        $route     = $this->getRouter()->generate('learningMarkbook.postMarkbook', ['userId' => $viewee->getId()]);
        $queryArgs = $this->getRequest()->query->getArrayCopy();

        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $viewee, func_get_args(), $queryArgs);

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
    public function postMarkbook(User $viewee): void
    {
        try {
            $request = $this->request->post['filter_selection'] ?: [];

            /**
             * @var null[]|Collection[]        $filters
             * @var Filter\Codec               $codec
             * @var Filter\Selection\Model[][] $selectionsPerFilter
             */
            [$filters, $codec, $selectionsPerFilter] = $this->convertRequestToFilters($request, $viewee, true);

            $assessmentIds = $this->assessmentIdFinder->findIdsByFilters($filters);
            $markbook      = $this->buildMarkbook($assessmentIds);

            $this->getResponse()->content->set($this->buildJsonOutput([
                'status'  => 200,
                'content' => $this->buildUnthemedHtmlOutput('@learningMarkbook/markbookInner.twig', [
                    'markbook'     => $markbook,
                    'viewee'       => $viewee,
                    'writable'     => $this->getAssessmentsWritability($markbook->getAssessments()),
                    'queryArgs'    => $codec->convertSelectionsToQueryString($selectionsPerFilter),
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
        } catch (\Throwable $e) {
            $this->captureException($e);
            $this->returnJsonError($e);
        }
    }


    /**
     * @param array $request  Indexed by filter.id, option.key
     * @param User  $viewee
     * @param bool  $saveToDB Should we persist these selections to the DB ?
     * @return array
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \ReflectionException If the specified class does not exist
     * @throws \DomainException If this class has not defined what entity it is filtering
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     */
    private function convertRequestToFilters(array $request, User $viewee, $saveToDB)
    {
        $selectionMapper = Filter\Selection\Mapper::getInstance(); // TODO pull from DI
        $page            = Filter\Page\Model::PAGE_CLASS_MARK_BOOK;
        $codec           = new Codec(Filter\Mapper::getInstance(), Filter\Selection\Mapper::getInstance());

        // Convert Request to Selection models
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);
        if ($saveToDB) {
            $selectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);
        }

        $filters = $this->filterFactoryFactory->convertSelectionsToFilters($selectionsPerFilter, $this->getUser(), $page);

        // Only one filter factory factory has this method: which one?
        $filters = $this->filterFactoryFactory->checkTeacherFolders($filters, $viewee);

        // Ensure that assessments are not Assessment Specs
        $filters = array_merge($filters, [
            Filter\Filters\Assessment\NullableContext\UnitSpec\Definition::class => [],
        ]);

        return [$filters, $codec, $selectionsPerFilter];
    }


    /**
     * Stream a filtered markbook in CSV format
     *
     * @api   GET /learning/markbook/:userId/export?{filterQueryArgs}
     * @param User $viewee
     * @throws \DomainException                      If attempting to get the slug from a filter where it has not been set
     * @throws Filter\BrokenFilterException          If a injected filter is unable to be processed
     * @throws \InvalidArgumentException             If any filter keys not recognised
     * @throws \ReflectionException                  If a specified filter class does not exist
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception           If an alias is incorrect
     */
    public function getMarkbookExport(User $viewee)
    {
        $filtersArray = $this->request->query->getArrayCopy();
        unset($filtersArray['page']);

        [$filters] = $this->convertRequestToFilters($filtersArray, $viewee, false);

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
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments, null, true);

        // Build a markbook
        $markbook = new Markbook($assessments, $this->defaultGradeGroup, $this->getUser());
        $markbook->sortAssessables();
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
