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
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Model;
use Twig\Error\Error;
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
     * @var Decimal
     */
    private Decimal $decimalHydrator;


    /**
     * @var MarkScheme\TotalMarkFractional
     */
    private MarkScheme\TotalMarkFractional $totalMarkScheme;


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
        /* Used to calculate human-readable and normalised marks for each student for capabilities and indicators
         */
        $this->decimalHydrator = new Decimal();
        $this->totalMarkScheme = new MarkScheme\TotalMarkFractional();
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
        ] = $this->buildMarkbookData($folder, $queryArgs, $assessmentIds);

        $csv = new Export\Csv(
            $studentMarks,
            $assessments,
            $exclusions,
            $overallMarks,
            $this->translator,
            empty($queryArgs['descriptorsOnly']),
        );

        $showDrafts = (bool)($queryArgs['showDrafts'] ?? false);

        $csv->export(
            sprintf(
                '%s%s - Rubric Markbook.csv',
                $showDrafts ? 'Draft - ' : '',
                $folder->getNameWithCodes()
            ),
            $showDrafts,
        );

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
     * Create a hash lookup of assessment details along with the rubric grid structure (caps, indicators) that were used
     * in each grid in each assessment. The lookup contains assessment weighting and links etc. common to all students
     * who undertook the assessment.
     *
     * The feedback grids for each student should align with the structure defined here: anything outside the set would
     * have been either bad data or invalidated data due to the grid changing structure midway through students doing
     * the assessment.
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
                rubric_instance.id                           AS rubric_instance_id,
                rubric_instance.title                        AS rubric_title,
                rubric_instance.rubric_capability_mark_type_id AS rubric_capability_mark_type_id,
                vr.capability_id                             AS capability_id,
                vr.capability_name                           AS capability_name,
                vr.capability_max_value                      AS capability_max_value,
                vr.indicator_id                              AS indicator_id,
                vr.indicator_name                            AS indicator_name,
                vr.indicator_max_value                       AS indicator_max_value
            FROM v_rubric vr
            INNER JOIN rubric_instance ON vr.rubric_instance_id = rubric_instance.id
            WHERE {$whereClause}
            ORDER BY
                vr.assessment_id,
                vr.rubric_instance_id,
                vr.capability_sequence,
                vr.capability_id,
                vr.indicator_sequence,
                vr.indicator_id
        ";

        $stmt = $this->internalDb->prepareQuery($sql, $pb->getParams());

        $decimalHydrator = new Decimal();
        $integerHydrator = new Integer();

        foreach ($stmt->getIterator() as $row) {
            /* First time seeing this assessment id - set up a container struct
             * We should only need to do this once per assessment
             */
            if (!isset($assessments[$row['assessment_id']])) {
                $assessments[$row['assessment_id']] = [
                    'name' => $row['assessment_name'],
                    'statusLink' => $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $row['assessment_id']]),
                    'maxMark' => $row['assessment_max_mark'],
                    'markType' => $row['assessment_mark_type'],
                    'weight' => $integerHydrator->hydrate($row['assessment_weight']),
                    /* gridData is a struct containing capability and indicators, grouped by assessment and rubric
                     * instance ids, like this:
                     *
                     * gridData => [
                     *         rubric instance id => [
                     *             rubric instance settings, like
                     *                 - is mark per capability
                     *                 - capability mark type id
                     *                 - capability grade group id
                     *             capabilities (capability data) => [
                     *                 capability mark type info
                     *                 indicators (indicator data) => [
                     *                 ]
                     *             ]
                     *         ]
                     *     ]
                     * ]
                     */
                    'gridData' => []
                ];
            }

            /* Set up this rubric instance container just once
             */
            if (!isset($assessments[$row['assessment_id']]['gridData'][$row['rubric_instance_id']])) {
                $assessments[$row['assessment_id']]['gridData'][$row['rubric_instance_id']] = [
                    'title' => $row['rubric_title'],
                    /* Mark Per Capability is used when the capability mark type is set to something other than None
                     * Be careful of this fact, cos it's easy to === instead of !== if you're not paying attention
                     */
                    'isMarkPerCapability' => MarkType::SLUG_NONE !== MarkType::idToSlug($row['rubric_capability_mark_type_id']),
                    'capabilities' => []
                ];
            }

            /* Set up this capability container just once
             */
            if (!isset($assessments[$row['assessment_id']]['gridData'][$row['rubric_instance_id']]['capabilities'][$row['capability_id']])) {
                /* Why is this even needed? Some reduction of $row['capability_max_value'] must result in something
                 * falsy (such as bad data) but idk what we saw in the wild to reinforce this theory.
                 */
                $capabilityMaxValue = $decimalHydrator->hydrate($row['capability_max_value']);

                $assessments[$row['assessment_id']]['gridData'][$row['rubric_instance_id']]['capabilities'][$row['capability_id']] = [
                    'id' => $row['capability_id'],
                    'name' => $row['capability_name'],
                    /* TODO find out what this means
                     */
                    'show' => $capabilityMaxValue && ($selectedCapabilities === null || in_array((int)$row['capability_id'], $selectedCapabilities, true)),
                    'indicators' => [],
                ];
            }

            /* Check whether the indicator was in the filter selections. If it was chosen, or if none of the filters
             * were chosen, then we need to include it in that stack. As per andyland filters, filtering nothing means
             * include everything.
             */
            if ($selectedIndicators === null || in_array((int)$row['indicator_id'], $selectedIndicators, true)) {
                if (!isset($assessments[$row['assessment_id']]['gridData'][$row['rubric_instance_id']]['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']])) {
                    $assessments[$row['assessment_id']]['gridData'][$row['rubric_instance_id']]['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']] = [
                        'name' => $row['indicator_name'],
                    ];
                }
            }
        }

        /* Apparently assessments are sorted alphabetically rather than by due date, which is a bit interesting: most of
         * the time we sort by due date. Jez concurs:
         *
         * > jez.gray 4/6/2026
         * > I had never noticed. Seems strange to me now that you point it out
         */
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
     * @param null|array $selectedCapabilities Null if rubricUsage filter not enabled for page
     * @param null|array $selectedIndicators Null if rubricUsage filter not enabled for page
     * @param array $exclusions
     * @param array $students
     * @return array
     * @throws RouteNotFound
     */
    private function retrievePublishedMarks(
        array $assessments,
        ?array $selectedCapabilities,
        ?array $selectedIndicators,
        array $exclusions,
        array $students
    ): array {
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

        /* Effectively a safe shortcut to an IN() clause
         */
        $assessmentIds = array_keys($assessments);

        $assessmentsPlaceholder = Builder::getArrayPlaceholders($assessmentIds, 'assessmentIds');
        $assessmentsParameters = Builder::getArrayParameters($assessmentIds, 'assessmentIds');

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
                    rubric_instance.rubric_capability_mark_type_id AS rubric_capability_mark_type_id,
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
                    WHERE sr.deleted_at IS NULL
                    AND sr.published_at IS NOT NULL
                    AND sr.review_route = :reviewRoute
                    AND sb.assessment_id IN {$assessmentsPlaceholder}
                    GROUP BY sb.assessment_id, sr.owner
                ) latest_published_feedback
                INNER JOIN v_rubric_mark vrm ON
                    vrm.feedback_date = latest_published_feedback.max_published_at AND
                    vrm.assessment_id = latest_published_feedback.assessment_id AND
                    vrm.student_id    = latest_published_feedback.owner
                INNER JOIN rubric_instance ON vrm.rubric_instance_id = rubric_instance.id
                INNER JOIN user u ON vrm.student_id = u.id
                WHERE {$outerWhere}
                AND rubric_instance.deleted_at IS NULL
                ORDER BY vrm.student_id, vrm.assessment_id, vrm.capability_id, vrm.indicator_id
            ",
            array_merge(
                $assessmentsParameters,
                $outerPb->getParams(),
                [
                    'reviewRoute' => ReturnedWork::REVIEW_ROUTE_INSTRUCTOR
                ]
            ),
        );

        foreach ($getAuthoritativeMarksQuery->getIterator() as $i => $row) {
            /* Set up a student node if it didn't already exist; we only need to set it up once
             */
            if (!isset($students[$row['student_id']])) {
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

            /* Cram in a link to the assessment's summary screen for a particular student if it didn't already exist
             */
            if (!isset($students[$row['student_id']]['assessments'][$row['assessment_id']])) {
                $students[$row['student_id']]['assessments'][$row['assessment_id']] = [
                    'summaryLink' => $this->routeGenerator->generate(
                        'learningAssessment.getSummary',
                        [
                            'studentId' => $row['student_id'],
                            'assessmentId' => $row['assessment_id'],
                        ],
                    )
                ];
            }

            /* If this student's been excluded, we can skip processing their data if it exists.
             *
             * TODO you can probably find a way to convert this to a ->notIn clause to restrict the database's result
             *  set instead of fetching it only to ignore it
             */
            if (isset($exclusions[$row['assessment_id']][$row['student_id']])) {
                continue;
            }

            /* Remember settings for the rubric instance that we're encountering, such as the rubric instance's mark per
             * capability setting, etc. if it hasn't already been set. We only need to set it up once.
             */
            if (!isset($students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']])) {
                $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']] = [
                    /* Obviously so you can see which grid is which, by its name, on the user interface
                     */
                    'title' => $row['rubric_title'],
                    /* Mark Per Capability is used when the capability mark type is set to something other than None
                     * Be careful of this fact, cos it's easy to === instead of !== if you're not paying attention
                     */
                    'isMarkPerCapability' => MarkType::SLUG_NONE !== MarkType::idToSlug($row['rubric_capability_mark_type_id']),
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
                $isMarkPerCapability = $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['isMarkPerCapability'];

                /* Calculate normalised and human-readable marks if relevant: depends on $isMarkPerCapability being true
                 */
                $humanReadableMark = null;
                $normalisedMark = null;

                if ($isMarkPerCapability) {
                    $marks = $this->generateMarkTuple(
                        $this->decimalHydrator->hydrate($row['capability_mark'] ?? null),
                        $this->decimalHydrator->hydrate($row['capability_max_value'] ?? null),
                    );

                    $normalisedMark = $marks['normalisedMark'];

                    /* This is already extant from our db rows: we don't need to reverse engineer it for capabilities
                     */
                    $humanReadableMark = $row['capability_human_readable_mark'];
                }

                $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']] = [
                    'name' => $row['capability_name'],
                    /* Published normalised and human-readable marks from the authoritative feedback
                     */
                    'published' => [
                        'normalisedMark' => $normalisedMark,
                        'humanReadableMark' => $humanReadableMark,
                        'selectedDescriptorText' => $row['descriptor_name'],
                    ],
                    /* Normalised and human-readable marks from the most recent feedback: these would typically be draft
                     * marks, but could end up being the same as the Published ones by sheer coincidence.
                     */
                    'draft' => [
                        'normalisedMark' => null,
                        'humanReadableMark' => null,
                        'selectedDescriptorText' => null,
                    ],
                    'indicators' => []
                ];
            }

            /* Check whether the indicator was in the filter selections. If it was chosen, or if none of the filters
             * were chosen, then we need to include it in that stack. As per andyland filters, filtering nothing means
             * include everything.
             */
            if ($selectedIndicators === null || in_array((int)$row['indicator_id'], $selectedIndicators, true)) {
                if (!isset($students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']])) {
                    $marks = $this->generateMarkTuple(
                        $this->decimalHydrator->hydrate($row['indicator_mark'] ?? null),
                        $this->decimalHydrator->hydrate($row['indicator_max_value'] ?? null),
                    );

                    $normalisedMark = $marks['normalisedMark'];
                    $humanReadableMark = $marks['humanReadableMark'];

                    $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']] = [
                        'name' => $row['indicator_name'],
                        /* Published normalised and human-readable marks from the authoritative feedback
                         */
                        'published' => [
                            'normalisedMark' => $normalisedMark,
                            'humanReadableMark' => $humanReadableMark,
                            'selectedDescriptorText' => $row['descriptor_name'],
                        ],
                        /* Normalised and human-readable marks from the most recent feedback: these would typically be draft
                         * marks, but could end up being the same as the Published ones by sheer coincidence.
                         */
                        'draft' => [
                            'normalisedMark' => null,
                            'humanReadableMark' => null,
                            'selectedDescriptorText' => null,
                        ],
                    ];
                }
            }
        }

        return $students;
    }

    /**
     * Synonymously with how retrievePublishedMarks() worked, but it takes the result of that method and fills in the
     * draft marks for capabilities and indicators using an almost-identical query, basically appending extra data to
     * the result of retrievePublishedMarks().
     *
     * v_most_recent_rubric_mark view is used instead of v_rubric_mark. Joining two views is probably a costly affair,
     * but it's cheaper than doing it any other way.
     *
     * TODO This is an egregious mega copypasta which surely could be refactored for brevity
     *
     * @param array<int,array> $assessments
     * @param null|array $selectedCapabilities Null if rubricUsage filter not enabled for page
     * @param null|array $selectedIndicators Null if rubricUsage filter not enabled for page
     * @param array $exclusions
     * @param array $students The array of student values which will already contian published, we are adding only the draft marks
     * @return array
     * @throws RouteNotFound
     */
    private function retrieveDraftMarks(
        array $assessments,
        ?array $selectedCapabilities,
        ?array $selectedIndicators,
        array $exclusions,
        array $students
    ): array {
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

        /* Effectively a safe shortcut to an IN() clause
         */
        $assessmentIds = array_keys($assessments);

        $assessmentsPlaceholder = Builder::getArrayPlaceholders($assessmentIds, 'assessmentIds');
        $assessmentsParameters = Builder::getArrayParameters($assessmentIds, 'assessmentIds');

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
                    rubric_instance.rubric_capability_mark_type_id AS rubric_capability_mark_type_id,
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
                    vrm.indicator_max_value            AS indicator_max_value,
                    vrm.feedback_date                  AS feedback_date
                FROM (
                    SELECT
                        sb.assessment_id,
                        sr.owner,
                        MAX(sr.updated_at) AS max_updated_at
                    FROM submission_return sr
                    INNER JOIN submission_box sb on sr.box_id = sb.id
                    WHERE sr.deleted_at IS NULL
                    AND sr.review_route = :reviewRoute
                    AND sb.assessment_id IN {$assessmentsPlaceholder}
                    GROUP BY sb.assessment_id, sr.owner
                ) latest_draft_feedback
                INNER JOIN v_most_recent_rubric_mark vrm ON
                    vrm.updated_date = latest_draft_feedback.max_updated_at AND
                    vrm.assessment_id = latest_draft_feedback.assessment_id AND
                    vrm.student_id    = latest_draft_feedback.owner AND
                    vrm.feedback_date IS NULL
                INNER JOIN rubric_instance ON vrm.rubric_instance_id = rubric_instance.id
                INNER JOIN user u ON vrm.student_id = u.id
                WHERE {$outerWhere}
                AND rubric_instance.deleted_at IS NULL
                ORDER BY vrm.student_id, vrm.assessment_id, vrm.capability_id, vrm.indicator_id
            ",
            array_merge(
                $assessmentsParameters,
                $outerPb->getParams(),
                [
                    'reviewRoute' => ReturnedWork::REVIEW_ROUTE_INSTRUCTOR
                ]
            ),
        );

        /* This adds draft marks into the $students struct we built earlier.
         *
         * We should be able to assume that $students already contains the superset of this data struct; a row from
         * v_most_recent_rubric_mark must be a subset of v_rubric_mark. Anything else seems broken to me
         *
         * TODO this repeats calculations that are destined to be the same; implement a skip-if-already-calculated to
         *  save some cycles, ie. a (student, assessment, capability id) and (student, assessment, indicator id)
         *  hash lookup so we can skip repeatedly calculating the same data
         */
        foreach ($getAuthoritativeMarksQuery->getIterator() as $i => $row) {
            /* Set up a student node if it didn't already exist; we only need to set it up once
             */
            if (!isset($students[$row['student_id']])) {
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

            /* Cram in a link to the assessment's summary screen for a particular student if it didn't already exist
             */
            if (!isset($students[$row['student_id']]['assessments'][$row['assessment_id']])) {
                $students[$row['student_id']]['assessments'][$row['assessment_id']] = [
                    'summaryLink' => $this->routeGenerator->generate(
                        'learningAssessment.getSummary',
                        [
                            'studentId' => $row['student_id'],
                            'assessmentId' => $row['assessment_id'],
                        ],
                    )
                ];
            }

            /* If this student's been excluded, we can skip processing their data if it exists.
             *
             * TODO you can probably find a way to convert this to a ->notIn clause to restrict the database's result
             *  set instead of fetching it only to ignore it
             */
            if (isset($exclusions[$row['assessment_id']][$row['student_id']])) {
                continue;
            }

            /* Remember settings for the rubric instance that we're encountering, such as the rubric instance's mark per
             * capability setting, etc. if it hasn't already been set. We only need to set it up once.
             */
            if (!isset($students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']])) {
                $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']] = [
                    /* Obviously so you can see which grid is which, by its name, on the user interface
                     */
                    'title' => $row['rubric_title'],
                    /* Mark Per Capability is used when the capability mark type is set to something other than None
                     * Be careful of this fact, cos it's easy to === instead of !== if you're not paying attention
                     */
                    'isMarkPerCapability' => MarkType::SLUG_NONE !== MarkType::idToSlug($row['rubric_capability_mark_type_id']),
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
             * Establish this node in the hash lookup if it didn't already exist: this occurs when there is no prior
             * published feedback (only a draft feedback exists)
             */
            if (!isset($students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']])) {
                $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']] = [
                    'name' => $row['capability_name'],
                    /* Published normalised and human-readable marks from the authoritative feedback
                     */
                    'published' => [
                        'normalisedMark' => null,
                        'humanReadableMark' => null,
                        'selectedDescriptorText' => null,
                    ],
                    /* Normalised and human-readable marks from the most recent feedback: these would typically be draft
                     * marks, but could end up being the same as the Published ones by sheer coincidence.
                     */
                    'draft' => [
                        'normalisedMark' => null,
                        'humanReadableMark' => null,
                        'selectedDescriptorText' => null,
                    ],
                    'indicators' => []
                ];
            }

            $isMarkPerCapability = $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['isMarkPerCapability'];

            /* Calculate normalised and human-readable marks if relevant: depends on $isMarkPerCapability being true
             */
            $humanReadableMark = null;
            $normalisedMark = null;

            if ($isMarkPerCapability) {
                $marks = $this->generateMarkTuple(
                    $this->decimalHydrator->hydrate($row['capability_mark'] ?? null),
                    $this->decimalHydrator->hydrate($row['capability_max_value'] ?? null),
                );

                $normalisedMark = $marks['normalisedMark'];

                /* This is already extant from our db rows: we don't need to reverse engineer it for capabilities
                 */
                $humanReadableMark = $row['capability_human_readable_mark'];
            }

            $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']]['draft'] = [
                'normalisedMark' => $normalisedMark,
                'humanReadableMark' => $humanReadableMark,
            ];

            /* Check whether the indicator was in the filter selections. If it was chosen, or if none of the filters
             * were chosen, then we need to include it in that stack. As per andyland filters, filtering nothing means
             * include everything.
             */
            if ($selectedIndicators === null || in_array((int)$row['indicator_id'], $selectedIndicators, true)) {
                /* Establish this node in the hash lookup if it didn't already exist: this occurs when there is no prior
                 * published feedback (only a draft feedback exists)
                 */
                if (!isset($students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']])) {
                    $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']] = [
                        'name' => $row['indicator_name'],
                        /* Published normalised and human-readable marks from the authoritative feedback
                         */
                        'published' => [
                            'normalisedMark' => null,
                            'humanReadableMark' => null,
                            'selectedDescriptorText' => null,
                        ],
                        /* Normalised and human-readable marks from the most recent feedback: these would typically be draft
                         * marks, but could end up being the same as the Published ones by sheer coincidence.
                         */
                        'draft' => [
                            'normalisedMark' => null,
                            'humanReadableMark' => null,
                            'selectedDescriptorText' => null,
                        ],
                    ];
                }

                $marks = $this->generateMarkTuple(
                    $this->decimalHydrator->hydrate($row['indicator_mark'] ?? null),
                    $this->decimalHydrator->hydrate($row['indicator_max_value'] ?? null),
                );

                $normalisedMark = $marks['normalisedMark'];
                $humanReadableMark = $marks['humanReadableMark'];

                $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']]['draft'] = [
                    'normalisedMark' => $normalisedMark,
                    'humanReadableMark' => $humanReadableMark,
                    'selectedDescriptorText' => $row['descriptor_name']
                ];
            }
        }

        return $students;
    }


    /**
     * Combine users who have marks with students and learners who are in the class or group, but have no marks yet
     *
     * @param Folder\Model $folder
     * @return array
     * @throws Database\Predicate\InvalidColumnException
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws PDOException
     * @throws UnderflowException
     */
    private function insertFolderMembers(Folder\Model $folder): array
    {
        $studentsAndLearners = $this->userAccessMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_READ, true, true);

        $studentMarks = [];

        foreach ($studentsAndLearners as $user) {
            $userId = $user->getId();

            if (!isset($studentMarks[$userId])) {
                /* Note that this is supposed to be the same data struct as seen i retrievePublishedMarks() and
                 * retrieveDraftMarks() where it progressively builds a hash lookup
                 *
                 * This method adds guys who didn't have any database marks (draft or otherwise) to ensure nobody is
                 * left out of the rubric markbook
                 */
                $studentMarks[$userId] = [
                    'firstname' => $user->getFirstname(),
                    'preferred_name' => $user->getPreferredName(),
                    'lastname' => $user->getLastname(),
                    'profileLink' => sprintf(
                        '/search/user/%s',
                        $userId
                    ),
                    'gridData' => [],
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
        $sql = "
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
            $userId = (int)$row['user_id'];

            $exclusions[$row['assessment_id']][$userId] = $userId;
        }

        return $exclusions;
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

        /* TODO replace
         */
        return $overallMarks;

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
     * Generates a struct containing a (normalised, human-readable) value corresponding to the numerator/denominator.
     * This is the exact same math that you can use for capability marks and indicator marks (idk why it was originally
     * copypasted) but do note that Capability Marks already come with their human-readable equivalent from the db.
     *
     * TODO Split to utility class
     * In future this can probably be split into its own utility class with a unit test so you can feed in a mark scheme
     * instead of assuming the Total Mark implementation.
     *
     * @param float|null $numerator
     * @param float|null $denominator
     * @return null[]
     */
    private function generateMarkTuple(?float $numerator, ?float $denominator): array
    {
        /* Set up initial conditions: by default these start as null
         */
        $marks = [
            'normalisedMark' => null,
            'humanReadableMark' => null,
        ];

        /* ...so we can exit early in the case that the denominator is null, hopefully preventing a mathematically
         * embarrassing runtime fatal in a randomly broken data set. Anything falsey will die here.
         */
        if (!$denominator) {
            return $marks;
        }

        /* The numerator might be null, so exit early in that case as well. It wasn't meaningful.
         * Note that zero is a perfectly valid value, so check for null specifically
         */
        if (null === $numerator) {
            return $marks;
        }

        /* We have meaningful numerator and denominators so it's time to math the numbers to do the stuff
         * TODO maybe we can round this to 3 decimals places but it probably has side effects in elsewhere
         */
        $normalisedMark = 100 * $numerator / $denominator;

        /* Note that this is circumstantially disregarded when used in capability mark aggregation: the capability's
         * human-readable mark already came from the database. Indicator human-readable marks, however, use this
         */
        $humanReadableMark = $this->totalMarkScheme
            ->setTotalMark($denominator)
            ->getHumanReadableMark($normalisedMark);

        return [
            'normalisedMark' => $normalisedMark,
            'humanReadableMark' => $humanReadableMark,
        ];
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
    private function buildMarkbookData(Folder\Model $folder, array $request, array $assessmentIds): array
    {
        /* These are the filter selections that were used from the ui
         */
        [$selectedCapabilities, $selectedIndicators] = $this->getRubricUsageSelections($request);

        /* Find a list of students who have been excluded from the set of assessments; we don't need to process their
         * results if they had any. We can skip them entirely
         */
        $exclusions = $this->getExclusions($assessmentIds);

        /* Construct each rubric grid's "structure" (capabilities and indicators) that students' feedbacks are supposed
         * to fulfil; some feedbacks might be incomplete, nonexistent, or uses an older version of a structure. This
         * ensures their feedbacks align as best as possible to what it is supposed to be -now-
         */
        $assessments = $this->buildAssessmentData($assessmentIds, $selectedCapabilities, $selectedIndicators);

        /* Set up the list of students who are in the class list at the moment; this is the basis of the $studentMarks
         * hash lookup that we'll progressively populate.
         *
         * This eventually manifests as a union of "guys who are in the class" and "guys who received feedback" for
         * the assessments in this folder. If we encounter feedback for a student who *was* in this class, a node will
         * spawn for them and proceed as if they *were* in the class right now.
         *
         * This process is repeated twice, almost verbatim, for published and draft marks.
         * TODO find a way to dedupe all this insanity
         */
        $studentMarks = $this->insertFolderMembers($folder);

        /* Augment $studentMarks with published data
         */
        $studentMarks = $this->retrievePublishedMarks(
            $assessments,
            $selectedCapabilities,
            $selectedIndicators,
            $exclusions,
            $studentMarks
        );

        /* Augment $studentMarks with draft data
         */
        $studentMarks = $this->retrieveDraftMarks(
            $assessments,
            $selectedCapabilities,
            $selectedIndicators,
            $exclusions,
            $studentMarks
        );

        /* At the bottom of the rubric markbook is a section called "Overall" which compiles the outcome per capability
         * and indicator combination from all the rubric grids. This aggregation should potentially be weighted against
         * the rubric weighting -per grid- but we'll get to that later.
         */
        $overallMarks = $this->buildOverallMarks($assessments, $studentMarks, $folder);

        return [
            $assessments,
            $exclusions,
            $studentMarks,
            $overallMarks
        ];
    }
}
