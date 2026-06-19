<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Markbook;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Alaress\Web\Controller\Base;
use Aura\Router;
use Aura\Router\Exception\RouteNotFound;
use DomainException;
use InvalidArgumentException;
use LogicException;
use PDOException;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Column\Decimal;
use Schoolbox\Column\Integer;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\Filter\Filters\Assessment\RubricUsage;
use Schoolbox\L10n\RosettaStone as Translator;
use Schoolbox\Learning\MarkScheme;
use Schoolbox\Learning\MarkScheme\Factory;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Model;
use Twig\Error\Error;
use UnderflowException;
use UnexpectedValueException;

use function array_keys;
use function array_merge;
use function count;
use function func_get_args;
use function implode;
use function in_array;
use function is_array;
use function strnatcmp;
use function uasort;

class Controller extends Base
{





    /**
     * Constructor
     *
     * @param IConnector                         $internalDb
     * @param UserAccessMapper                   $userAccessMapper
     * @param Filter\IFactoryFactory             $filterFactoryFactory
     * @param Filter\Selection\Mapper            $filterSelectionMapper
     * @param Filter\Filters\Assessment\IdFinder $assessmentIdFinder
     * @param Filter\Mapper                      $filterMapper
     * @param Translator                         $translator
     */
    public function __construct(
        private IConnector $internalDb,
        private UserAccessMapper $userAccessMapper,
        private Filter\IFactoryFactory $filterFactoryFactory,
        private Filter\Selection\Mapper $filterSelectionMapper,
        private Filter\Filters\Assessment\IdFinder $assessmentIdFinder,
        private Filter\Mapper $filterMapper,
        private Translator $translator
    ) {
    }


    /**
     * Renders a filter panel and placeholder that will asynchronously load the rubric report
     *
     * @param Model $folder Folder for this class
     * @throws RouteNotFound
     * @throws Error
     * @since 190
     * @api   GET /learning/rubricMarkbook/{folderId}
     */
    public function getMarkbook(Folder\Model $folder): void
    {
        $queryArgs = $this->requestQuery->getArrayCopy();
        unset($queryArgs['page']);

        $page        = Filter\Page\Model::PAGE_RUBRIC_MARK_BOOK;
        $route       = $this->routeGenerator->generate('rubricMarkbook.postMarkbook', ['folderId' => $folder->getId()]);
        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $this->getUser(), func_get_args(), $queryArgs);

        $this->response = $this->responseFromTemplate(
            '@rubricMarkbook/markbookOuter.twig',
            [
                'folder'      => $folder,
                'filterPanel' => $filterPanel,
            ],
            'Rubric Markbook for ' . $folder->getName(),
            'submission'
        );
    }


    /**
     * Output the rubric report as a cool CSV for extra-Schoolbox data wrangling,
     * for those that are into that sort of thing.
     *
     * @param Model $folder Folder for this class
     * @throws Exception
     * @throws RouteNotFound
     * @since 192
     * @api   GET /learning/rubricMarkbook/export/{folderId}
     */
    public function getMarkbookExport(Folder\Model $folder): void
    {
        // This report may take a while
        $this->extendExecutionTime();

        $queryArgs = $this->requestQuery->getArrayCopy();

        unset($queryArgs['page']);

        $assessmentIds = $this->getAssessmentIds($folder->getId(), $queryArgs);

        [
            $assessments,
            $exclusions,
            $studentMarks,
            $overallMarks
        ] = (new Dataset(
            $this->internalDb,
            new Decimal(),
            new MarkScheme\TotalMarkFractional(),
            new Factory($this->getConfigLoader()),
            $this->filterMapper,
            $this->userAccessMapper,
            $this->routeGenerator
        ))->buildMarkbookData($folder, $queryArgs, $assessmentIds);

        /* Optional params from the frontend. The Show Drafts checkbox state is obeyed.
         */
        $showDrafts = (bool)($queryArgs['showDrafts'] ?? false);
        $descriptorsOnly = (bool)($queryArgs['descriptorsOnly'] ?? false);

        $csv = new Export\Csv(
            $assessments,
            $exclusions,
            $studentMarks,
            $overallMarks,
            $this->translator,
            $descriptorsOnly,
            $showDrafts,
        );

        /* TODO replace with the Filename Codec usage which for some reason I can't find in this branch
         */
        $filename = sprintf(
            '%s%s - Rubric Markbook.csv',
            $showDrafts ? 'Draft - ' : '',
            $folder->getNameWithCodes()
        );

        $csv->export($filename);

        exit;
    }


    /**
     * Displays a list of rubric marks for students and learners associated with the specified folder
     *
     * @param Model $folder Folder for this class
     * @throws Error
     * @throws Exception
     * @throws RouteNotFound
     * @api   POST /learning/rubricMarkbook/{folderId}
     * @since 190
     */
    public function postMarkbook(Folder\Model $folder): void
    {
        // This report may take a while
        $this->extendExecutionTime();

        $request       = $this->requestPost['filter_selection'] ?: [];
        $assessmentIds = $this->getAssessmentIds($folder->getId(), $request);
        $codec         = new Codec($this->filterMapper, $this->filterSelectionMapper);

        [
            $assessments,
            $exclusions,
            $studentMarks,
            $overallMarks
        ] = (new Dataset(
            $this->internalDb,
            new Decimal(),
            new MarkScheme\TotalMarkFractional(),
            new Factory($this->getConfigLoader()),
            $this->filterMapper,
            $this->userAccessMapper,
            $this->routeGenerator
        ))->buildMarkbookData($folder, $request, $assessmentIds);

        $this->response = $this->responseAsJson([
            'status'  => 200,
            'content' => $this->responseFromTemplateUnthemed(
                '@rubricMarkbook/markbookInner.twig',
                [
                    'folder'      => $folder,
                    'isFiltered'  => (new Filter\RequestChecker())->isFilteredRequest($request),
                    'students'    => $studentMarks,
                    'assessments' => $assessments,
                    'exclusions'  => $exclusions,
                    'overall'     => $overallMarks,
                    'queryArgs'   => $codec->convertSelectionsToQueryString(
                        $codec->convertRequestToSelections(
                            $this->getUser(),
                            Filter\Page\Model::PAGE_RUBRIC_MARK_BOOK,
                            $request
                        )
                    ),
                ]
            )->getBody()->getContents(),
        ]);
    }


    /**
     * Returns the set of assessments that match the filter panel selections and folder id
     *
     * @param int   $folderId
     * @param array $request
     * @return int[]
     * @throws DomainException
     * @throws Filter\BrokenFilterException
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws UnexpectedValueException
     * @throws LogicException
     */
    private function getAssessmentIds(int $folderId, array $request): array
    {
        $page                = Filter\Page\Model::PAGE_RUBRIC_MARK_BOOK;
        $codec               = new Codec($this->filterMapper, $this->filterSelectionMapper);
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);
        $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);

        // Assessments must belong to folder, be due-works, and not assessment specs.
        $keyDueWork = Filter\Filters\Assessment\AssessmentType\Definition::KEY_DUE_WORK;
        $hardCoded  = [
            Filter\Filters\Assessment\Folder\StudentInteractions\Definition::class => [$folderId => $folderId],
            Filter\Filters\Assessment\AssessmentType\Definition::class             => [$keyDueWork => $keyDueWork],
            Filter\Filters\Assessment\NullableContext\UnitSpec\Definition::class   => [],
        ];
        $dynamic    = $this->filterFactoryFactory->convertSelectionsToFilters($selectionsPerFilter, $this->getUser(), $page);
        $filters    = array_merge($hardCoded, $dynamic);

        return $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());
    }
}
