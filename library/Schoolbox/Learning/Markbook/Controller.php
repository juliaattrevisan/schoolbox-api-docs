<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Markbook;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Assessment\Term;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Feedback\Visibility;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\MarkScheme\Factory;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Model as FolderModel;
use Schoolbox\User;

use function func_get_args;

class Controller extends Base
{
    /** @var AssessmentMapperProxy */
    private $assessmentMapperProxy;

    /** @var Term\Mapper */
    private $termMapper;

    /** @var Access */
    private $access;

    /** @var Filter\Filters\Assessment\IdFinder */
    private $assessmentIdFinder;

    /** @var Filter\IFactoryFactory */
    private $filterFactoryFactory;


    /**
     * @param AssessmentMapperProxy              $assessmentMapperProxy
     * @param Term\Mapper                        $termMapper
     * @param Access                             $access
     * @param Filter\Filters\Assessment\IdFinder $assessmentIdFinder
     * @param Filter\IFactoryFactory             $filterFactoryFactory
     */
    public function __construct(
        AssessmentMapperProxy $assessmentMapperProxy,
        Term\Mapper $termMapper,
        Access $access,
        Filter\Filters\Assessment\IdFinder $assessmentIdFinder,
        Filter\IFactoryFactory $filterFactoryFactory
    ) {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->termMapper            = $termMapper;
        $this->access                = $access;
        $this->assessmentIdFinder    = $assessmentIdFinder;
        $this->filterFactoryFactory  = $filterFactoryFactory;
    }


    /**
     * Render the shell of the markbook for the specified class.
     *
     * This renders the filter panel, but not its contents: those are rendered
     * by `postMarkbookForClass`.
     *
     * @api   GET /learning/markbook/class/:folderId?{filterQueryArgs}
     * @since 20.1
     *
     * @param FolderModel $folder
     * @throws RouteNotFound If unable to generate the redirect route
     */
    public function getMarkbookForClass(FolderModel $folder): void
    {
        // This is now the final location, not the redirect
        // Ignore viewee: always use current user
        $page      = Filter\Page\Model::PAGE_CLASS_MARK_BOOK;
        $route     = $this->getRouter()->generate(
            'learningMarkbook.postMarkbookForClass',
            [
                'folder'   => $folder,
                'folderId' => $folder->getId(),
            ]
        );
        $queryArgs = $this->getRequest()->query->getArrayCopy();

        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $this->getUser(), func_get_args(), $queryArgs);

        $pageTitle = 'Class Markbook';

        $this->getResponse()->content->set(
            $this->buildHtmlOutput('@learningMarkbook/markbookOuter.twig', [
                'folder'      => $folder,
                'filterPanel' => $filterPanel,
            ])->setTab('submission')->setTitle($pageTitle)
        );
    }


    /**
     * Receives a set of filter panel selections and render a filtered markbook
     * for the given class.
     *
     * @api   POST /learning/markbook/class/:folderId
     * @since 20.1
     *
     * @param FolderModel $folder
     */
    public function postMarkbookForClass(FolderModel $folder): void
    {
        try {
            $request = $this->request->post['filter_selection'] ?: [];

            /**
             * @var null[]|Collection[]        $filters
             * @var Filter\Codec               $codec
             * @var Filter\Selection\Model[][] $selectionsPerFilter
             */
            [$filters, $codec, $selectionsPerFilter] = $this->convertRequestToFilters($request, true);

            $assessmentIds = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());
            $markbook      = $this->buildMarkbook($assessmentIds, $folder);

            /**
             * need to get an list of the students by id, with their ranking
             */
            $rank = $markbook->getRanking();

            $this->getResponse()->content->set($this->buildJsonOutput([
                'status'  => 200,
                'content' => $this->buildUnthemedHtmlOutput('@learningMarkbook/markbookInner.twig', [
                    'markbook'              => $markbook,
                    'folder'                => $folder,
                    'rank'                  => $rank,
                    'rankEnabled'           => (bool)$this->getConfigLoader()->get('view_rank'),
                    'writable'              => $this->getAssessmentsWritability($markbook->getAssessments()),
                    'queryArgs'             => $codec->convertSelectionsToQueryString($selectionsPerFilter),
                    'availableVisibilities' => Visibility::getAvailableVisibilities((int)$this->getConfigLoader()->get('submission_visibility_max')),
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
     * Redirect to the markbook for the specified class and filter to a term
     *
     * @api   GET /learning/markbook/class/:folderId/term/:termCode
     * @since 20.1
     *
     * @param FolderModel $folder
     * @param Term\Model  $term
     * @throws RouteNotFound If unable to generate the redirect route
     */
    public function getMarkbookForClassAndTerm(FolderModel $folder, Term\Model $term): void
    {
        // TODO override the filter instead of redirecting
        $this->buildMarkbookRedirect($folder, $term);
    }


    /**
     * @param FolderModel     $folder
     * @param null|Term\Model $term
     * @throws RouteNotFound If unable to generate the redirect route
     */
    private function buildMarkbookRedirect(FolderModel $folder, ?Term\Model $term): void
    {
        $route = $this->getRouter()->generate(
            'learningMarkbook.getMarkbookForClass',
            [
                'folder'   => $folder,
                'folderId' => $folder->getId(),
            ]
        );

        $filterMapper    = Filter\Mapper::getInstance();
        $selectionMapper = Filter\Selection\Mapper::getInstance();

        $selections = new GenericCollection(GenericCollection::class);
        $filters    = $filterMapper->findForEntity(\Schoolbox\Learning\Assessment\DataModel\Mapper::class);
        foreach ($filters as $filter) {
            if ($term && Filter\Filters\Assessment\Term\Due\Definition::class === $filter->getClass()) {
                $selections[$filter->getId()]                 = $selectionMapper->createCollection();
                $selections[$filter->getId()][$term->getId()] = $this->createSelectionModel(
                    $selectionMapper,
                    $filter,
                    (string)$term->getId()
                );
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
    private function createSelectionModel(Filter\Selection\Mapper $selectionMapper, Filter\Model $filter, $key): Filter\Selection\Model
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
     * @param array $request  Indexed by filter.id, option.key
     * @param bool  $saveToDB Should we persist these selections to the DB ?
     * @return array
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \ReflectionException If the specified class does not exist
     * @throws \DomainException If this class has not defined what entity it is filtering
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     */
    private function convertRequestToFilters(array $request, bool $saveToDB): array
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

        // Ensure that assessments are not Assessment Specs
        $filters = array_merge($filters, [
            Filter\Filters\Assessment\NullableContext\UnitSpec\Definition::class => [],
        ]);

        return [$filters, $codec, $selectionsPerFilter];
    }


    /**
     * Stream a filtered markbook for a folder in CSV format
     *
     * @api   GET /learning/markbook/class/:folderId/export?{filterQueryArgs}
     * @since 20.1
     *
     * @param FolderModel $folder
     *
     * @throws \DomainException                      If attempting to get the slug from a filter where it has not been set
     * @throws Filter\BrokenFilterException          If a injected filter is unable to be processed
     * @throws \InvalidArgumentException             If any filter keys not recognised
     * @throws \ReflectionException                  If a specified filter class does not exist
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception           If an alias is incorrect
     */
    public function getMarkbookExportForClass(FolderModel $folder): void
    {
        // This report may take a while
        $this->extendExecutionTime();

        $filtersArray = $this->request->query->getArrayCopy();
        unset($filtersArray['page']);

        [$filters] = $this->convertRequestToFilters($filtersArray, false);

        $assessmentIds = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());
        $markbook      = $this->buildMarkbook($assessmentIds, $folder);

        $csvExporter = new Export\CSV((bool)$this->getConfigLoader()->get('view_rank'));
        $csvExporter->export($markbook, sprintf('%s - Class Markbook.csv', $folder->getName()));
        exit;
    }


    /**
     * Builds a markbook for this folder.
     *
     * @param array       $assessmentIds
     * @param FolderModel $folder
     * @return Markbook
     */
    private function buildMarkbook(array $assessmentIds, FolderModel $folder): Markbook
    {
        // Fetch assessments & participants
        if (empty($assessmentIds)) {
            $assessments = $this->assessmentMapperProxy->createCollection();
        } else {
            $assessments = $this->assessmentMapperProxy->findAllByFolderIdForReview(
                $folder->getId(),
                null,
                ['ids' => $assessmentIds]
            );
        }

        // Build a markbook
        $markSchemeFactory = new Factory($this->getConfigLoader());
        $overallMarkScheme = $markSchemeFactory->spawnForFolder($folder, $this->getUser()->getRole()->isStaff());

        $markbook = new Markbook($assessments, $overallMarkScheme, $this->getUser());
        $markbook->sortAssessables();
        return $markbook;
    }


    /**
     * @param GenericCollection|IAssessable[] $assessments
     * @return bool[]
     */
    private function getAssessmentsWritability(GenericCollection $assessments): array
    {
        /* Determine which assessments the user should be able to mark.
         *
         * Can only mark an assessment if:
         * * Assessment is/was not due in a term that is archived, and
         * * User has write+ perm to the page
         */
        /** @var bool[] $writable Indexed by assessment.id */
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
    protected function returnJsonError($e): void
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
    private function isAssessmentMarkable(User $user, IAssessable $assessment, GenericCollection $terms): bool
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
