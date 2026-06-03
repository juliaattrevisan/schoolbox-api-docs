<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Markbook;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Web\Controller\Base;
use Aura\Router;
use DomainException;
use InvalidArgumentException;
use LogicException;
use PDOException;
use RangeException;
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
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use UnderflowException;
use UnexpectedValueException;

use function array_key_exists;
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
     * @var IConnector
     */
    private $internalDb;

    /**
     * Data source for group lists
     *
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /** @var Filter\IFactoryFactory */
    private $filterFactoryFactory;

    /** @var Filter\Selection\Mapper */
    private $filterSelectionMapper;

    /** @var Filter\Filters\Assessment\IdFinder */
    private $assessmentIdFinder;

    /** @var Filter\Mapper */
    private $filterMapper;


    /**
     * @var Translator
     */
    private $translator;


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
        IConnector $internalDb,
        UserAccessMapper $userAccessMapper,
        Filter\IFactoryFactory $filterFactoryFactory,
        Filter\Selection\Mapper $filterSelectionMapper,
        Filter\Filters\Assessment\IdFinder $assessmentIdFinder,
        Filter\Mapper $filterMapper,
        Translator $translator
    ) {
        $this->internalDb            = $internalDb;
        $this->userAccessMapper      = $userAccessMapper;
        $this->filterFactoryFactory  = $filterFactoryFactory;
        $this->filterSelectionMapper = $filterSelectionMapper;
        $this->assessmentIdFinder    = $assessmentIdFinder;
        $this->filterMapper          = $filterMapper;
        $this->translator            = $translator;
    }


    /**
     * Renders a filter panel and placeholder that will asynchronously load the rubric report
     *
     * @api   GET /learning/rubricMarkbook/{folderId}
     * @since 190
     * @param Folder\Model $folder Folder for this class
     * @throws Database\Predicate\InvalidColumnException
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Router\Exception\RouteNotFound
     * @throws UnderflowException
     * @throws DomainException
     * @throws UnexpectedValueException
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
     * @api   GET /learning/rubricMarkbook/export/{folderId}
     * @since 192
     * @param Folder\Model $folder Folder for this class
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
            $students,
            $overallMarks
        ] = $this->buildMarkbookData($folder, $queryArgs, $assessmentIds);

        $csv = new Export\Csv(
            $students,
            $assessments,
            $exclusions,
            $overallMarks,
            $this->translator,
            empty($queryArgs['descriptorsOnly']),
        );
        $showDrafts = (bool)($queryArgs['showDrafts'] ?? false);
        $csv->export(
            ($showDrafts ? 'Draft - ' : '') . $folder->getNameWithCodes() . ' - Rubric Markbook.csv',
            $showDrafts,
        );
        exit;
    }


    /**
     * Displays a list of rubric marks for students and learners associated with the specified folder
     *
     * @api   POST /learning/rubricMarkbook/{folderId}
     * @since 190
     * @param Folder\Model $folder Folder for this class
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnderflowException
     * @throws UnexpectedValueException
     * @throws LogicException
     * @throws Router\Exception\RouteNotFound
     * @throws RangeException
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
            $students,
            $overallMarks
        ] = $this->buildMarkbookData($folder, $request, $assessmentIds);

        $this->response = $this->responseAsJson([
            'status'  => 200,
            'content' => $this->responseFromTemplateUnthemed(
                '@rubricMarkbook/markbookInner.twig',
                [
                    'folder'      => $folder,
                    'isFiltered'  => (new Filter\RequestChecker())->isFilteredRequest($request),
                    'students'    => $students,
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
     * Returns the Capabilities and Indicators selected in the filter panel.
     *
     * The RubricUsage (aka Capability/Indicator) filter filters both the
     * assessment set, and the rendering of the page.
     *
     * @param array $request
     * @return array
     * @throws UnexpectedValueException
     */
    private function getRubricUsageSelections(array $request): array
    {
        /** @var Filter\Model[] $filters */
        $filters               = $this->filterMapper->findByClass(RubricUsage\Definition::class)->indexBy('class');
        $rubricUsageFilter     = $filters[RubricUsage\Definition::class];
        $rubricUsageSelections = $request[$rubricUsageFilter->getId()] ?? null;

        // Is filter not present on page at all?
        if ($rubricUsageSelections === null) {
            return [null, null];
        }

        // Is filter present, but no options are available?
        if ($rubricUsageSelections === "") {
            return [[], []];
        }

        $selectedCapabilities = $selectedIndicators = [];
        $rubricUsageCodec     = new RubricUsage\Codec();

        $countSelected = 0;
        foreach ($rubricUsageSelections as $key => $val) {
            if ($val === '1') {
                $countSelected++;
            }
        }
        $noneSelected = $countSelected === 0;

        foreach ($rubricUsageSelections as $key => $val) {
            if ($val === '1' || $noneSelected) {
                [$capId, $indId] = $rubricUsageCodec->decode($key);
                if ($indId) {
                    $selectedIndicators[] = $indId;
                } else {
                    $selectedCapabilities[] = $capId;
                }
            }
        }
        return [$selectedCapabilities, $selectedIndicators];
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


    /**
     * Fetch assessment data, and return it indexed by assessment ID
     *
     * @param int[]      $assessmentIds
     * @param null|array $selectedCapabilities Null if rubricUsage filter not enabled for page
     * @param null|array $selectedIndicators   Null if rubricUsage filter not enabled for page
     * @return array
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Router\Exception\RouteNotFound
     * @throws UnderflowException
     */
    private function buildAssessmentData(array $assessmentIds, ?array $selectedCapabilities, ?array $selectedIndicators): array
    {
        $assessments = [];

        // If no assessments match filters, short-circuit out.
        if (!count($assessmentIds)) {
            return $assessments;
        }

        // Is the rubric usage filter enabled for the page?
        $filteringByRubricUsage = is_array($selectedCapabilities) || is_array($selectedIndicators);

        // If enabled, but nothing selected? (probably because no options available)
        if ($filteringByRubricUsage && !count($selectedCapabilities) && !count($selectedIndicators)) {
            return $assessments;
        }

        $pb = (new Database\Predicate\Builder($this->internalDb->getQuoter(), ''))->in('vr.assessment_id', $assessmentIds);

        if ($filteringByRubricUsage) {
            $usagePb = new Database\Predicate\Builder($this->internalDb->getQuoter(), '');
            if (is_array($selectedCapabilities) && count($selectedCapabilities)) {
                $usagePb->in('vr.capability_id', $selectedCapabilities);
            }
            if (is_array($selectedIndicators) && count($selectedIndicators)) {
                $usagePb->in('vr.indicator_id', $selectedIndicators);
            }
            $pb->any($usagePb);
        }

        $whereClause = implode(' AND ', $pb->getWhere());

        $sql  = "
            SELECT
                vr.assessment_id                             AS assessment_id,
                vr.assessment_grade_group_id                 AS assessment_grade_group_id,
                vr.assessment_title                          AS assessment_name,
                vr.assessment_max_mark                       AS assessment_max_mark,
                vr.assessment_mark_type                      AS assessment_mark_type,
                vr.assessment_grade_group_id                 AS assessment_grade_group_id,
                vr.assessment_weight                         AS assessment_weight,
                vr.capability_id                             AS capability_id,
                vr.capability_name                           AS capability_name,
                vr.capability_max_value                      AS capability_max_value,
                vr.capability_mark_type_id                   AS capability_mark_type_id,
                vr.capability_grade_group_id                 AS capability_grade_group_id,
                IF(vr.capability_mark_type_id = :none, 0, 1) AS assessment_is_mark_per_capability,
                vr.indicator_id                              AS indicator_id,
                vr.indicator_name                            AS indicator_name,
                vr.indicator_max_value                       AS indicator_max_value
            FROM v_rubric vr
            WHERE {$whereClause}
            ORDER BY
                vr.assessment_id,
                vr.capability_sequence,
                vr.capability_id,
                vr.indicator_sequence,
                vr.indicator_id
        ";
        $stmt = $this->internalDb->prepareQuery($sql, ['none' => MarkType::TYPE_NONE] + $pb->getParams());

        $capabilities = [];
        $indicators   = [];

        $lastObservedAssessmentId = null;
        $lastObservedCapabilityId = null;
        $decimalHydrator          = new Decimal();
        $integerHydrator          = new Integer();

        foreach ($stmt->getIterator() as $row) {
            if ($lastObservedAssessmentId !== $row['assessment_id']) {
                // Has the assessment we're looking at changed? Reset the capabilities
                $capabilities = [];
            }

            if ($lastObservedCapabilityId !== $row['capability_id'] || $lastObservedAssessmentId !== $row['assessment_id']) {
                // Has the capability we're looking at changed? Reset the indicators
                $indicators = [];
            }

            if ($selectedIndicators === null || in_array((int)$row['indicator_id'], $selectedIndicators, true)) {
                $indicators[$row['indicator_id']] = [
                    'name' => $row['indicator_name'],
                ];
            }

            $capabilityMaxValue                  = $decimalHydrator->hydrate($row['capability_max_value']);
            $capabilities[$row['capability_id']] = [
                'name'         => $row['capability_name'],
                'indicators'   => $indicators,
                'show'         => $capabilityMaxValue && ($selectedCapabilities === null || in_array((int)$row['capability_id'], $selectedCapabilities, true)),
            ];
            ;
            // Stuff everything about the assessment into an array
            $assessments[$row['assessment_id']] = [
                'name'           => $row['assessment_name'],
                'statusLink'     => $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $row['assessment_id']]),
                'maxMark'        => $row['assessment_max_mark'],
                'markType'       => $row['assessment_mark_type'],
                'capabilityMarkTypeId'     => $row['capability_mark_type_id'],
                'capabilityGradeGroupId'   => $row['capability_grade_group_id'],
                'isMarkPerCapability' => $row['assessment_is_mark_per_capability'],
                'weight'         => $integerHydrator->hydrate($row['assessment_weight']),
                'capabilities'   => $capabilities,
            ];

            $lastObservedAssessmentId = $row['assessment_id'];
            $lastObservedCapabilityId = $row['capability_id'];
        }

        uasort(
            $assessments,
            static function (array $a, array $b): int {
                return strnatcmp($a['name'], $b['name']);
            }
        );
        return $assessments;
    }


    /**
     * Find each student in a folder and their marks (if extant), and return an array indexed by student ID
     *
     * @param array<int,array> $assessments
     * @param null|array       $selectedCapabilities Null if rubricUsage filter not enabled for page
     * @param null|array       $selectedIndicators   Null if rubricUsage filter not enabled for page
     * @return array
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws UnderflowException
     */
    private function retrieveDatabaseMarks(array $assessments, ?array $selectedCapabilities, ?array $selectedIndicators): array
    {
        $students = [];
        // Don't load marks if no assessments.
        if (!count($assessments)) {
            return $students;
        }

        // Is the rubric usage filter enabled for the page?
        $filteringByRubricUsage = is_array($selectedCapabilities) || is_array($selectedIndicators);

        // If enabled, but nothing selected? (probably because no options available)
        if ($filteringByRubricUsage && !count($selectedCapabilities) && !count($selectedIndicators)) {
            return $assessments;
        }

        $innerPb = (new Database\Predicate\Builder($this->internalDb->getQuoter(), ''))
            ->isNull('sr.deleted_at')
            ->notNull('sr.published_at')
            ->equals('sr.review_route', ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->in('sb.assessment_id', array_keys($assessments));

        $innerWhere = implode(' AND ', $innerPb->getWhere());

        $outerPb = (new Database\Predicate\Builder($this->internalDb->getQuoter(), ''))
            ->equals('vrm.feedback_type', ReturnedWork::REVIEW_ROUTE_INSTRUCTOR);

        if ($filteringByRubricUsage) {
            $usagePb = new Database\Predicate\Builder($this->internalDb->getQuoter(), '');
            if (is_array($selectedCapabilities) && count($selectedCapabilities)) {
                $usagePb->in('vrm.capability_id', $selectedCapabilities);
            }
            if (is_array($selectedCapabilities) && count($selectedIndicators)) {
                $usagePb->in('vrm.indicator_id', $selectedIndicators);
            }
            $outerPb->any($usagePb);
        }

        $outerWhere = implode(' AND ', $outerPb->getWhere());

        $getAuthoritativeMarksQuery = $this->internalDb->prepareQuery(
            "
                SELECT
                    vrm.assessment_id                  AS assessment_id,
                    vrm.student_id                     AS student_id,
                    u.firstname                        AS student_firstname,
                    u.preferred_name                   AS student_preferred_name,
                    u.lastname                         AS student_lastname,
                    rubric_instance.id                 AS rubric_instance_id,
                    rubric_instance.title              AS rubric_title,
                    vrm.capability_id                  AS capability_id,
                    vrm.capability_name                AS capability_name,
                    vrm.capability_mark                AS capability_mark,
                    vrm.capability_human_readable_mark AS capability_human_readable_mark,
                    vrm.capability_max_value           AS capability_max_value,
                    vrm.capability_mark_type_id        AS capability_mark_type_id,
                    vrm.capability_grade_group_id      AS capability_grade_group_id,
                    vrm.descriptor_name                AS descriptor_name,
                    vrm.indicator_id                   AS indicator_id,
                    vrm.indicator_name                 AS indicator_name,
                    vrm.indicator_mark                 AS indicator_mark,
                    vrm.indicator_max_value            AS indicator_max_value
                FROM (
                    SELECT
                        sb.assessment_id,
                        sr.owner,
                        MAX(sr.published_at) AS max_published_at
                    FROM submission_return sr
                    INNER JOIN submission_box sb on sr.box_id = sb.id
                    WHERE {$innerWhere}
                    GROUP BY sb.assessment_id, sr.owner
                ) latest_feedback
                INNER JOIN v_rubric_mark vrm ON
                    vrm.feedback_date = latest_feedback.max_published_at AND
                    vrm.assessment_id = latest_feedback.assessment_id AND
                    vrm.student_id    = latest_feedback.owner
                INNER JOIN rubric_instance ON vrm.rubric_instance_id = rubric_instance.id
                INNER JOIN user u ON vrm.student_id = u.id
                WHERE {$outerWhere}
                AND rubric_instance.deleted_at IS NULL
                ORDER BY vrm.student_id, vrm.assessment_id, vrm.capability_id, vrm.indicator_id
            ",
            array_merge($innerPb->getParams(), $outerPb->getParams()),
        );

        foreach ($getAuthoritativeMarksQuery->getIterator() as $i => $row) {
            /* Set up a student node if it didn't already exist; we only need to set it up once
             */
            if (!isset($row['student_id'])) {
                $students[$row['student_id']] = [
                    'firstname' => $row['student_firstname'],
                    'preferred_name' => $row['student_preferred_name'],
                    'lastname' => $row['student_lastname'],
                    'profileLink' => sprintf(
                        '/search/user/%s',
                        $row['student_id']
                    ),
                    /* gridData is a struct containing capability and indicator marks, grouped by assessment and rubric
                     * instance ids, like this:
                     *
                     * gridData => [
                     *     assessment id => [
                     *         rubric instance id => [
                     *             rubric instance settings, like
                     *                 - is mark per capability
                     *                 - capability mark type id
                     *                 - capability grade group id
                     *             capabilities (capability data) => [
                     *                 capability mark type info
                     *                 capability mark values if relevant to this grid's settings
                     *                 indicators (indicator data) => [
                     *                     database marks
                     *                     max marks
                     *                     selected descriptor text
                     *                 ]
                     *             ]
                     *         ]
                     *     ]
                     * ]
                     */
                    'gridData' => []
                ];
            }

            /* Remember settings for the rubric instance that we're encountering, such as the rubric instance's mark per
             * capability setting, etc. if it hasn't already been set. We only need to set it up once.
             */
            if (!isset($students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']])) {
                $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']] = [
                    /* Obviously so you can see which grid is which, by its name, on the user interface
                     */
                    'title' => $row['rubric_title'],
                    'isMarkPerCapability' => null,
                    /* Capabilities' Mark Type and Grade Group can be represented differently to their Assessments'
                     * Mark Type and Grade Group
                     */
                    'capabilityMarkTypeId' => $row['capability_mark_type_id'],
                    'capabilityGradeGroupId' => $row['capability_grade_group_id'],
                    /* Capability Mark values. This would be relevant if you chose a Capability Mark Type to be
                     * something other than NONE such as Percent, Letter Grade, Total Mark, etc.
                     */
                    'capabilities' => [
                        /* This contains the descriptors that were selected for each indicator and exists per
                         * capability: it's here for illustrative purposes and commented out for this reason
                         */
                        // 'indicators' => []
                    ],
                ];
            }

            /* Remember capability mark type output per grid: we only need to set this up once per capability
             */
            if (!isset($students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']])) {
                $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']] = [
                    'name' => $row['capability_name'],
                    'databaseMark' => $row['capability_mark'], // TODO depend on isMarkPerCapability
                    'humanReadableMark' => $row['capability_human_readable_mark'], // TODO depend on isMarkPerCapability
                    'maxMark' => $row['capability_max_value'],
                    'indicators' => []
                ];
            }

            /* Check whether the indicator was in the filter selections. If it was chosen, or if none of the filters
             * were chosen, then we need to include it in that stack. As per andyland filters, filtering nothing means
             * include everything.
             */
            if ($selectedIndicators === null || in_array((int)$row['indicator_id'], $selectedIndicators, true)) {
                if (!isset($students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']])) {
                    $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']] = [
                        'name' => $row['indicator_name'],
                        'databaseMark' => $row['indicator_mark'],
                        'maxMark' => $row['indicator_max_value'],
                        'selectedDescriptorText' => $row['descriptor_name'],
                    ];
                }
            }
        }
new \Alaress_Probe($students);
        return $students;
    }

    /**
     * Find each student in a folder and their draft marks (if extant), and return an array indexed by student ID
     *
     * dupe of retrieveDatabaseMarks
     *
     * @param array<int,array> $assessments
     * @param null|array       $selectedCapabilities Null if rubricUsage filter not enabled for page
     * @param null|array       $selectedIndicators   Null if rubricUsage filter not enabled for page
     * @param array            $students             The array of student values which will already contian published, we are adding only the draft marks
     * @return array
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws UnderflowException
     */
    private function retrieveDatabaseDraftMarks(array $assessments, ?array $selectedCapabilities, ?array $selectedIndicators, array $students): array
    {
        // Don't load marks if no assessments.
        if (!count($assessments)) {
            return $students;
        }

        // Is the rubric usage filter enabled for the page?
        $filteringByRubricUsage = is_array($selectedCapabilities) || is_array($selectedIndicators);

        // If enabled, but nothing selected? (probably because no options available)
        if ($filteringByRubricUsage && !count($selectedCapabilities) && !count($selectedIndicators)) {
            return $assessments;
        }

        $innerPb    = (new Database\Predicate\Builder($this->internalDb->getQuoter(), ''))
            ->isNull('sr.deleted_at')
            ->equals('sr.review_route', ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->in('sb.assessment_id', array_keys($assessments));

        $innerWhere = implode(' AND ', $innerPb->getWhere());

        $outerPb = (new Database\Predicate\Builder($this->internalDb->getQuoter(), ''))
            ->equals('vrm.feedback_type', ReturnedWork::REVIEW_ROUTE_INSTRUCTOR);

        if ($filteringByRubricUsage) {
            $usagePb = new Database\Predicate\Builder($this->internalDb->getQuoter(), '');
            if (is_array($selectedCapabilities) && count($selectedCapabilities)) {
                $usagePb->in('vrm.capability_id', $selectedCapabilities);
            }
            if (is_array($selectedCapabilities) && count($selectedIndicators)) {
                $usagePb->in('vrm.indicator_id', $selectedIndicators);
            }
            $outerPb->any($usagePb);
            $outerPb->isNull('rubric_instance.deleted_at');
        }

        $outerWhere = implode(' AND ', $outerPb->getWhere());

        $getAuthoritativeMarksQuery = $this->internalDb->prepareQuery(
            "
                SELECT
                    vrm.assessment_id                  AS assessment_id,
                    vrm.student_id                     AS student_id,
                    u.firstname                        AS student_firstname,
                    u.preferred_name                   AS student_preferred_name,
                    u.lastname                         AS student_lastname,
                    vrm.capability_id                  AS capability_id,
                    vrm.capability_mark                AS capability_mark,
                    vrm.capability_human_readable_mark AS capability_human_readable_mark,
                    vrm.capability_max_value           AS capability_max_value,
                    vrm.capability_mark_type_id        AS capability_mark_type_id,
                    vrm.capability_grade_group_id      AS capability_grade_group_id,
                    vrm.descriptor_name                AS descriptor_name,
                    vrm.indicator_id                   AS indicator_id,
                    vrm.indicator_mark                 AS indicator_mark,
                    vrm.indicator_max_value            AS indicator_max_value,
                    vrm.feedback_date                  AS feedback_date
                FROM (
                    SELECT
                        sb.assessment_id,
                        sr.owner,
                        MAX(sr.updated_at) AS max_updated_at
                    FROM submission_return sr
                    INNER JOIN submission_box sb on sr.box_id = sb.id
                    WHERE {$innerWhere}
                    GROUP BY sb.assessment_id, sr.owner
                ) latest_feedback
                INNER JOIN v_most_recent_rubric_mark vrm ON
                    vrm.updated_date = latest_feedback.max_updated_at AND
                    vrm.assessment_id = latest_feedback.assessment_id AND
                    vrm.student_id    = latest_feedback.owner AND
                    vrm.feedback_date is null
                INNER JOIN rubric_instance ON vrm.rubric_instance_id = rubric_instance.id
                INNER JOIN user u ON vrm.student_id = u.id
                WHERE {$outerWhere}
                ORDER BY vrm.student_id, vrm.assessment_id, vrm.capability_id, vrm.indicator_id
            ",
            array_merge($innerPb->getParams(), $outerPb->getParams()),
        );

        $assessmentsForStudent = [];
        $capabilityMarks       = [];
        $indicatorMarks        = [];

        $lastObservedAssessmentId = null;
        $lastObservedCapabilityId = null;
        $lastObservedStudentId    = null;

        foreach ($getAuthoritativeMarksQuery->getIterator() as $i => $row) {
            if ($lastObservedStudentId !== $row['student_id']) {
                // Has the student we're looking at changed? Reset the list of assessments for them
                $assessmentsForStudent = [];
            }

            if ($lastObservedAssessmentId !== $row['assessment_id']) {
                // Has the assessment we're looking at changed? Reset the capabilities
                $capabilityMarks = [];
            }

            if ($lastObservedCapabilityId !== $row['capability_id']) {
                // Has the capability we're looking at changed? Reset the indicators
                $indicatorMarks = [];
            }

            if ($selectedIndicators === null || in_array((int)$row['indicator_id'], $selectedIndicators, true)) {
                $indicatorMarks[$row['indicator_id']] = [
                    'databaseMark'           => $row['indicator_mark'],
                    'maxMark'                => $row['indicator_max_value'],
                    'selectedDescriptorText' => $row['descriptor_name'],
                ];
            }

            $capData = [
                'indicators' => $indicatorMarks,
            ];
            $isMarkPerCapability = $assessments[$row['assessment_id']]['isMarkPerCapability'] ?? false;
            if ($assessments[$row['assessment_id']]['capabilities'][$row['capability_id']]['show']) {
                    $capData['databaseMark']      = $isMarkPerCapability ? $row['capability_mark'] : null;
                    $capData['humanReadableMark'] = $isMarkPerCapability ? $row['capability_human_readable_mark'] : null;
                    $capData['maxMark']           = $row['capability_max_value'];

                    /* Capabilities' Mark Type and Grade Group can be represented differently to
                     * their Assessments' Mark Type and Grade Group
                     */
                    $capData['capabilityMarkTypeId']   = $row['capability_mark_type_id'];
                    $capData['capabilityGradeGroupId'] = $row['capability_grade_group_id'];
            }
            $capabilityMarks[$row['capability_id']] = $capData;

            $assessmentsForStudent[$row['assessment_id']] = [
                'capabilities' => $capabilityMarks,
            ];

            if (isset($students[$row['student_id']])) {
                /**
                 * student array already exists (they have some published marks, just add these draft marks)
                 * Stuff everything about the students marks into an array
                 */
                $students[$row['student_id']]['assessmentsLatest'] = $assessmentsForStudent;
            } else {
                /**
                 * Student doesn't exist (only has draft marks, need to build the base student array
                 */
                $students[$row['student_id']] = [
                    'firstname'      => $row['student_firstname'],
                    'preferred_name' => $row['student_preferred_name'],
                    'lastname'       => $row['student_lastname'],
                    'assessmentsLatest'    => $assessmentsForStudent,
                    'profileLink'    => '/search/user/' . $row['student_id'],
                ];
            }

            $lastObservedAssessmentId = $row['assessment_id'];
            $lastObservedCapabilityId = $row['capability_id'];
            $lastObservedStudentId    = $row['student_id'];
        }
        return $students;
    }


    /**
     * Collate the human readable marks for both published and latest (draft) student marks then return them in a student marks array
     *
     * @param array $students
     * @param array $assessments
     * @return array
     */
    private function collateHumanReadableMarks(array $students, array $assessments): array
    {
        foreach ($students as $studentId => $student) {
            $students = $this->generateHumanReadableMarks($students, $student['assessmentsPublished'] ?? [], $studentId, 'assessmentsPublished');
            $students = $this->generateHumanReadableMarks($students, $student['assessmentsLatest'] ?? [], $studentId, 'assessmentsLatest');
        }

        return $students;
    }


    /**
     * Combine users who have marks with students and learners who are in the class or group, but have no marks yet
     *
     * @param array        $studentMarks
     * @param Folder\Model $folder
     * @return array
     * @throws Database\Predicate\InvalidColumnException
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnderflowException
     */
    private function insertFolderMembers(array $studentMarks, Folder\Model $folder): array
    {
        $studentsAndLearners = $this->userAccessMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_READ, true, true);
        foreach ($studentsAndLearners as $user) {
            $userId = $user->getId();
            if (!isset($studentMarks[$userId])) {
                $studentMarks[$userId] = [
                    'firstname'   => $user->getFirstname(),
                    'lastname'    => $user->getLastname(),
                    'profileLink' => '/search/user/' . $userId,
                    'assessments' => [],
                ];
            }
        }

        uasort(
            $studentMarks,
            static function (array $a, array $b): int {
                $last = strnatcasecmp($a['lastname'] ?? '', $b['lastname'] ?? '');
                if ($last === 0) {
                    return strnatcasecmp($a['firstname'], $b['firstname']);
                }
                return $last;
            }
        );

        return $studentMarks;
    }


    /**
     * Returns the ids of users who have been excluded via CAP, grouped by assessment id
     *
     * @param array $assessmentIds
     * @return int[][]
     * @throws Database\Exception
     */
    private function getExclusions(array $assessmentIds): array
    {
        $exclusions = [];
        if (!count($assessmentIds)) {
            return $exclusions;
        }

        $bindings = Database\Predicate\Builder::getBindings($assessmentIds, 'a_id');
        $sql      = "
            SELECT
                a.id      AS assessment_id,
                p.user_id AS user_id
            FROM participant p
            INNER JOIN participant_plan pp ON p.participant_plan_id = pp.id
            INNER JOIN assessment a        ON pp.assessment_id      = a.id
            WHERE
                pp.assessment_id IN ({$bindings->getPlaceholder()}) AND
                p.excluded_at IS NOT NULL AND
                pp.deleted_at IS NULL AND
                a.deleted_at IS NULL;
        ";

        $statement = $this->internalDb->executeQuery($sql, $bindings->getParams());
        /** @noinspection PhpAssignmentInConditionInspection */
        while ($row = $statement->fetch()) {
            $exclusions[$row['assessment_id']][] = (int)$row['user_id'];
        }
        return $exclusions;
    }


    /**
     * Insert summary page links for each student/assessment
     *
     * @param mixed[] $studentMarks  Indexed by userId
     * @param int[]   $assessmentIds Indexed by assessmentId
     * @param int[][] $exclusions    Indexed by assessmentId, userId
     * @return array
     * @throws Router\Exception\RouteNotFound
     */
    private function insertSummaryPageLinks(array $studentMarks, array $assessmentIds, array $exclusions): array
    {
        // Ensure each student has a summary link, even if they have no marks
        foreach (array_keys($studentMarks) as $studentId) {
            foreach ($assessmentIds as $assessmentId) {
                if (array_key_exists($assessmentId, $exclusions) && in_array($studentId, $exclusions[$assessmentId], true)) {
                    unset($studentMarks[$studentId]['assessments'][$assessmentId]);
                    continue;
                }
                $summaryLink = $this->routeGenerator->generate(
                    'learningAssessment.getSummary',
                    [
                        'studentId'    => $studentId,
                        'assessmentId' => $assessmentId,
                    ]
                );
                $studentMarks[$studentId]['assessments'][$assessmentId]['summaryLink'] = $summaryLink;
            }
        }

        return $studentMarks;
    }


    /**
     * Iterates student individual marks to return overall student marks for capabilities and indicators
     *
     * @param array $assessments
     * @param array $linkedMarks
     * @param Folder\Model $folder
     * @return array
     * @throws InvalidArgumentException
     * @throws RangeException
     */
    private function buildOverallMarks(array $assessments, array $linkedMarks, Folder\Model $folder): array
    {
        $overallMarks = $this->aggregateMarks($assessments, $linkedMarks);

        $summated = $this->summateAggregatedMarks($overallMarks, $folder);

        foreach ($summated as &$capability) {
            uasort($capability['indicators'], [$this, 'sortByName']);
        }
        unset($capability);
        uasort($summated, [$this, 'sortByName']);

        return $summated;
    }

    /**
     * Quick alpha sort function for indicators and capabilities summary section
     * @param array $a
     * @param array $b
     * @return int
     */
    private function sortByName($a, $b)
    {
        return strnatcasecmp($a['name'], $b['name']);
    }

    /**
     * Resolves the aggregated counts to summate final capability/indicator marks
     *
     * @param array $overallMarks
     * @param Folder\Model $folder
     * @return array
     * @throws InvalidArgumentException
     * @throws RangeException
     */
    private function summateAggregatedMarks(array $overallMarks, Folder\Model $folder): array
    {
        /**
         * Logic for the mark scheme...
         *
         * If there is no year level on the class page, we should default back to the system default.
         * If there are multiple year levels on the class page, and they agree (we should use their grade group)
         * If there are multiple year levels on the class page, and they do NOT agree (all different grade groups), we should fall back to the system default grade group.
         */
        $markSchemeFactory = new Factory($this->getConfigLoader());
        $markScheme = $markSchemeFactory->spawnForFolder($folder, true);

        foreach ($overallMarks as $capabilityId => $capabilityMarks) {
            foreach ($capabilityMarks['marks'] as $studentId => $sums) {
                $sumNormalised      = $sums['sumNormalised'];
                $sumWeight          = $sums['sumWeight'];
                $latestSumNormalised = $sums['latestSumNormalised'];
                $latestSumWeight     = $sums['latestSumWeight'];
                if (!$sumWeight && !$latestSumWeight) {
                    // nothing exists, so remove this...
                    unset($overallMarks[$capabilityId]['marks'][$studentId]);
                } else {
                    // only dump things into the array that actually exist...
                    $overallMarks[$capabilityId]['marks'][$studentId] = [];
                    if ($sumWeight) {
                        $normalised = $sumNormalised / $sumWeight;
                        $overallMarks[$capabilityId]['marks'][$studentId]['normalised'] = $normalised;
                        $overallMarks[$capabilityId]['marks'][$studentId]['humanReadable'] = $markScheme->getHumanReadableMark($normalised);
                        $overallMarks[$capabilityId]['marks'][$studentId]['latestNormalised'] = $normalised;
                        $overallMarks[$capabilityId]['marks'][$studentId]['latestHumanReadable'] = $markScheme->getHumanReadableMark($normalised);
                    }
                    if ($latestSumWeight) {
                        $latestNormalised                                  = $latestSumNormalised / $latestSumWeight;
                        $overallMarks[$capabilityId]['marks'][$studentId]['latestNormalised'] = $latestNormalised;
                        $overallMarks[$capabilityId]['marks'][$studentId]['latestHumanReadable'] = $markScheme->getHumanReadableMark($latestNormalised);
                    }
                }
            }
            foreach ($capabilityMarks['indicators'] as $indicatorId => $indicatorMarks) {
                foreach ($indicatorMarks['marks'] as $studentId => $sums) {
                    $sumNormalised = $sums['sumNormalised'];
                    $sumWeight     = $sums['sumWeight'];
                    $latestSumNormalised = $sums['latestSumNormalised'];
                    $latestSumWeight     = $sums['latestSumWeight'];
                    if (!$sumWeight && !$latestSumWeight) {
                        // nothing exists, so remove this...
                        unset($overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]);
                    } else {
                        // only dump things into the array that actually exist...
                        $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId] = [];
                        if ($sumWeight) {
                            $normalised = $sumNormalised / $sumWeight;
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]['normalised'] = $normalised;
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]['humanReadable'] = $markScheme->getHumanReadableMark($normalised);
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]['latestNormalised'] = $normalised;
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]['latestHumanReadable'] = $markScheme->getHumanReadableMark($normalised);
                        }
                        if ($latestSumWeight) {
                            $latestNormalised = $latestSumNormalised / $latestSumWeight;
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]['latestNormalised'] = $latestNormalised;
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]['latestHumanReadable'] = $markScheme->getHumanReadableMark($latestNormalised);
                        }
                    }
                }
            }
        }
        return $overallMarks;
    }


    /**
     * Iterates individual student marks to build the weighted sums per capability and indicator
     *
     * @param array $assessments
     * @param array $linkedMarks
     * @return array
     */
    private function aggregateMarks(array $assessments, array $linkedMarks): array
    {
        $overallMarks = [];

        foreach ($assessments as $assessmentId => $assessmentData) {
            $assessmentWeight = $assessmentData['weight'];
            foreach ($assessmentData['capabilities'] as $capabilityId => $capabilityData) {
                // First time we've seen this capability?
                if (!array_key_exists($capabilityId, $overallMarks)) {
                    $overallMarks[$capabilityId] = [
                        'name'       => $capabilityData['name'],
                        'show'       => $capabilityData['show'],
                        'marks'      => [],
                        'indicators' => [],
                    ];
                }

                // Show the capability if we are showing it for any assessment
                $overallMarks[$capabilityId]['show'] = $overallMarks[$capabilityId]['show'] || $capabilityData['show'];

                // Only calculate capability marks if the assessment has weight and we are rendering the capability.
                if ($assessmentWeight && $overallMarks[$capabilityId]['show']) {
                    foreach ($linkedMarks as $studentId => $studentData) {
                        // First time we've see this student for this capability?
                        if (!array_key_exists($studentId, $overallMarks[$capabilityId]['marks'])) {
                            $overallMarks[$capabilityId]['marks'][$studentId] = [
                                'sumNormalised'      => null,
                                'sumWeight'          => null,
                                'latestSumNormalised' => null,
                                'latestSumWeight'     => null,
                            ];
                        }
                        // Does student have a mark for this capability for this assessment?
                        $capabilityMark = $studentData['assessmentsPublished'][$assessmentId]['capabilities'][$capabilityId]['normalisedMark'] ?? null;
                        if ($capabilityMark !== null) {
                            $overallMarks[$capabilityId]['marks'][$studentId]['sumNormalised'] += $capabilityMark * $assessmentWeight;
                            $overallMarks[$capabilityId]['marks'][$studentId]['sumWeight']     += $assessmentWeight;
                            $overallMarks[$capabilityId]['marks'][$studentId]['latestSumNormalised'] += $capabilityMark * $assessmentWeight;
                            $overallMarks[$capabilityId]['marks'][$studentId]['latestSumWeight']     += $assessmentWeight;
                        }
                        // Latest Marks
                        $latestCapabilityMark = $studentData['assessmentsLatest'][$assessmentId]['capabilities'][$capabilityId]['normalisedMark'] ?? null;
                        if ($latestCapabilityMark !== null) {
                            $overallMarks[$capabilityId]['marks'][$studentId]['latestSumNormalised'] += $latestCapabilityMark * $assessmentWeight;
                            $overallMarks[$capabilityId]['marks'][$studentId]['latestSumWeight']     += $assessmentWeight;
                        }
                    }
                }

                foreach ($capabilityData['indicators'] as $indicatorId => $indicatorData) {
                    // First time we've seen this indicator?
                    if (!array_key_exists($indicatorId, $overallMarks[$capabilityId]['indicators'])) {
                        $overallMarks[$capabilityId]['indicators'][$indicatorId] = [
                            'name'  => $indicatorData['name'],
                            'marks' => [],
                        ];
                    }
                    if (!$assessmentWeight) {
                        // Don't aggregate marks if the assessment lacks weighting
                        continue;
                    }

                    foreach ($linkedMarks as $studentId => $studentData) {
                        // First time we've see this student for this indicator?
                        if (!array_key_exists($studentId, $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'])) {
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId] = [
                                'sumNormalised'      => null,
                                'sumWeight'          => null,
                                'latestSumNormalised' => null,
                                'latestSumWeight'     => null,
                            ];
                        }
                        // Does student have a mark for this indicator for this assessment?
                        $indicatorMark = $studentData['assessmentsPublished'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['normalisedMark'] ?? null;
                        if ($indicatorMark !== null) {
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]['sumNormalised'] += $indicatorMark * $assessmentWeight;
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]['sumWeight']     += $assessmentWeight;
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]['latestSumNormalised'] += $indicatorMark * $assessmentWeight;
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]['latestSumWeight']     += $assessmentWeight;
                        }
                        // Latest Marks
                        $latestIndicatorMark = $studentData['assessmentsLatest'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['normalisedMark'] ?? null;
                        if ($latestIndicatorMark !== null) {
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]['latestSumNormalised'] += $latestIndicatorMark * $assessmentWeight;
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]['latestSumWeight']     += $assessmentWeight;
                        }
                    }
                }
            }
        }
        return $overallMarks;
    }

    /**
     * Work out the human readable marks from the raw marks and insert them into the student marks array
     *
     * @param array  $students      array of students and marks calculate and build onto
     * @param array  $studentMarks  array of the database marks for the student
     * @param int    $studentId     tmp student ID to work out where to put these marks info
     * @param string $field         string of the mark type (published / latest (draft))
     * @return array
     */
    private function generateHumanReadableMarks(array $students, array $studentMarks, int $studentId, string $field): array
    {
        $decimalHydrator = new Decimal();
        $totalMarkScheme = new MarkScheme\TotalMarkFractional();
        foreach (($studentMarks) as $assessmentId => $assessmentMarks) {
            foreach (($assessmentMarks['capabilities'] ?? []) as $capabilityId => $capabilityMarks) {
                $capabilityDatabaseMark      = $decimalHydrator->hydrate($capabilityMarks['databaseMark'] ?? null);
                $capabilityMaxMark           = $decimalHydrator->hydrate($capabilityMarks['maxMark'] ?? null);
                $humanReadableCapabilityMark = null;
                $normalisedCapabilityMark    = null;
                if ($capabilityMaxMark) {
                    $normalisedCapabilityMark = $capabilityDatabaseMark !== null
                        ? ($capabilityDatabaseMark / $capabilityMaxMark) * 100
                        : null;
                    $humanReadableCapabilityMark = $capabilityMarks['humanReadableMark'] ?? null;
                }
                $students[$studentId][$field][$assessmentId]['capabilities'][$capabilityId]['humanReadableMark'] = $humanReadableCapabilityMark;
                $students[$studentId][$field][$assessmentId]['capabilities'][$capabilityId]['normalisedMark']    = $normalisedCapabilityMark;

                foreach (($capabilityMarks['indicators'] ?? []) as $indicatorId => $indicatorMarks) {
                    $indicatorDatabaseMark      = $decimalHydrator->hydrate($indicatorMarks['databaseMark'] ?? null);
                    $indicatorMaxMark           = $decimalHydrator->hydrate($indicatorMarks['maxMark'] ?? null);
                    $humanReadableIndicatorMark = null;
                    $normalisedIndicatorMark    = null;

                    if ($indicatorMaxMark) {
                        $normalisedIndicatorMark    = $indicatorDatabaseMark !== null
                            ? ($indicatorDatabaseMark / $indicatorMaxMark) * 100
                            : null;
                        $humanReadableIndicatorMark = $totalMarkScheme->setTotalMark($indicatorMaxMark)->getHumanReadableMark($normalisedIndicatorMark);
                    }

                    $students[$studentId][$field][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['humanReadableMark'] = $humanReadableIndicatorMark;
                    $students[$studentId][$field][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['normalisedMark']    = $normalisedIndicatorMark;
                }
            }
        }
        return $students;
    }

    /**
     * Build data required by both the HTML and CSV form of the rubric markbook.
     *
     * @param Folder\Model $folder
     * @param array        $request
     * @param array        $assessmentIds
     * @return array 0: array A list of assessment data
     *               1: int[][] A list of IDs of students excluded from each
     *                  assessment, grouped by assessment ID
     *               2: array A list of summary links to assessments for
     *                  each student partaking
     *               3: array A summation of marks for each capability
     *
     * @throws Mapper\Exception
     * @throws Router\Exception\RouteNotFound
     */
    private function buildMarkbookData(Folder\Model $folder, array $request, $assessmentIds): array
    {
        [$selectedCapabilities, $selectedIndicators] = $this->getRubricUsageSelections($request);
        $assessments   = $this->buildAssessmentData($assessmentIds, $selectedCapabilities, $selectedIndicators);
        $databaseMarks = $this->retrieveDatabaseMarks($assessments, $selectedCapabilities, $selectedIndicators);
        $databaseMarks = $this->retrieveDatabaseDraftMarks($assessments, $selectedCapabilities, $selectedIndicators, $databaseMarks);

        $derivedMarks  = $this->collateHumanReadableMarks($databaseMarks, $assessments);
        $studentMarks  = $this->insertFolderMembers($derivedMarks, $folder);
        $exclusions    = $this->getExclusions($assessmentIds);
        $linkedMarks   = $this->insertSummaryPageLinks($studentMarks, $assessmentIds, $exclusions);
        $overallMarks  = $this->buildOverallMarks($assessments, $linkedMarks, $folder);
        return [$assessments, $exclusions, $linkedMarks, $overallMarks];
    }
}
