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
use Schoolbox\GenericCollection;
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
     * Redirect to the markbook for the specified class
     *
     * @param int $folderId
     * @param User|null $viewee
     * @throws RouteNotFound If unable to generate the redirect route
     */
    public function getMarkbookForClass($folderId, User $viewee = null)
    {
        $user = $viewee ?: $this->getUser();
        $currentTerm = $this->termMapper->findForToday();
        $route = $this->getRouter()->generate('learningMarkbook.getMarkbook', ['userId' => $user->getId()]);
        $getArgs = "f={$folderId}&td={$currentTerm->getId()}&a=&p=&uis=&wt=&wk=";
        $this->getResponse()->redirect->to("{$route}?{$getArgs}");
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
            $filters              = $this->generateFilters($postFilterSelections);
            $assessmentIds        = $this->assessmentIdFinder->findIdsByFilters($filters);
            $markbook             = $this->buildMarkbook($assessmentIds);

            $this->getResponse()->content->set($this->buildJsonOutput([
                'status'  => 200,
                'content' => $this->buildUnthemedHtmlOutput('@learningMarkbook/markbookInner.twig', [
                    'markbook'     => $markbook,
                    'viewee'       => $viewee,
                    'writable'     => $this->determineWritableAssessments($markbook->getAssessments()),
                    'pendingLabel' => $this->getPendingLabel(),
                    'queryArgs'    => $this->buildQueryArgs($filters),
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
     * Stream a filtered markbook in CSV format
     *
     * @api   GET /learning/markbook/:userId/export?{filterQueryArgs}
     * @param User $viewee
     * @throws \DomainException If attempting to get the slug from a filter where it has not been set
     * @throws Filter\BrokenFilterException If a injected filter is unable to be processed
     * @throws \InvalidArgumentException If any filter keys not recognised
     * @throws \ReflectionException If a specified filter class does not exist
     */
    public function getMarkbookExport(User $viewee)
    {
        $filtersArray = $this->request->query->getArrayCopy();
        unset($filtersArray['page']);

        $filtersById   = $this->transformQueryArgsToFilters($filtersArray);
        $filters       = $this->generateFilters($filtersById);
        $assessmentIds = $this->assessmentIdFinder->findIdsByFilters($filters);
        $markbook      = $this->buildMarkbook($assessmentIds);

        $csvExporter = new Export\CSV();
        $filename    = preg_replace('/[^\w-(),]/', '_', "Markbook_{$viewee->getFullName()}.csv");
        $csvExporter->export($markbook, $filename);
        exit;
    }


    /**
     * Convert our GET query arg string into format required
     *
     * @param array $filtersArray
     * @return array
     * @throws \DomainException If attempting to get the slug from a filter where it has not been set
     * @todo - consider refactor architecture to use ID instead of slug to make this unnecessary, or at least hide behind clean interface. SA-3379  SA-3201
     */
    private function transformQueryArgsToFilters(array $filtersArray)
    {
        // TODO clean all this up
        foreach ($filtersArray as $slug => $filter) {
            $filtersArray[$slug] = '' === $filter ? [] : explode(',', $filter);
        }

        // convert to indexed by filter.ID instead of filter.definition.SLUG
        $filtersById        = [];
        $filterMapper       = Filter\Mapper::getInstance();
        $allFilterInstances = $filterMapper->findAll();
        foreach ($allFilterInstances as $instance) {
            foreach ($filtersArray as $slug => $keys) {
                $definition = $instance->getClass();
                if ($definition::getSlug() === $slug) {
                    $filtersById[$instance->getId()] = array_combine($keys, array_fill(0, count($keys), '1'));
                    break;
                }
            }
        }
        return $filtersById;
    }


    /**
     * Convert filter panel selections into filters
     *
     * @param array $filterSelections
     * @return null[]|Collection[]
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \ReflectionException If the specified class does not exist
     */
    private function generateFilters(array $filterSelections)
    {
        $page = FilterOption\Model::PAGE_MARK_BOOK;

        $keyConverters        = $this->filterFactoryFactory->buildKeyConverters($page);
        $selectionsPerFilter  = Filter\Selection\Mapper::getInstance()->updateSelections($keyConverters, $this->getUser(), $page, $filterSelections);
        $selectionTransformer = new AssessmentFilter\Transformer($keyConverters, $selectionsPerFilter);
        $filters              = $selectionTransformer->getFilters();

        // Explicitly inject filter to ensure we don't get assessments that live directly in UnitSpecs,
        // regardless of any filter selections send by user
        $filters[AssessmentFilter\NullableContext\UnitSpec\Definition::SLUG] = new Collection();

        return $filters;
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
    private function determineWritableAssessments(GenericCollection $assessments)
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
