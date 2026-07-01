<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Markbook;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder;
use Aura\Router;
use Aura\Router\Exception\RouteNotFound;
use Aura\Router\Generator;
use InvalidArgumentException;
use PDOException;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Column\Decimal;
use Schoolbox\Column\Integer;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\RubricUsage;
use Schoolbox\Learning\MarkScheme;
use Schoolbox\Learning\MarkScheme\Factory;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Model;
use UnderflowException;
use UnexpectedValueException;

class OverallDataset
{
    /**
     * These ar regularly-used strings for important nodes in the hash lookup
     * I've compiled them up here in case of typos throughout the hash lookup which is REALLY ANNOYING to find/fix
     */
    private const string MARK_NODE_PUBLISHED = 'published';
    private const string MARK_NODE_DRAFT = 'draft';


    /**
     * These are repeatedly-used indexes in the hash lookup. They are calculated synonymously with each other.
     */
    private const array MARK_NODES = [self::MARK_NODE_PUBLISHED, self::MARK_NODE_DRAFT];


    /**
     * @param IConnector $internalDb
     * @param Decimal $decimalHydrator Used to calculate human-readable and normalised marks for each student for capabilities and indicators
     * @param TotalMarkFractional $totalMarkScheme Used to calculate human-readable and normalised marks for each student for capabilities and indicators
     * @param Factory $markSchemeFactory
     * @param Filter\Mapper $filterMapper
     * @param UserAccessMapper $userAccessMapper
     * @param Generator $routeGenerator
     */
    public function __construct(
        private IConnector $internalDb,
        private Decimal $decimalHydrator,
        private MarkScheme\TotalMarkFractional $totalMarkScheme,
        private Factory $markSchemeFactory,
        private Filter\Mapper $filterMapper,
        private UserAccessMapper $userAccessMapper,
        private Router\Generator $routeGenerator,
    ) {
    }


    /**
     * Build data required by both the HTML and CSV form of the rubric markbook.
     *
     * @param Model|null $folder
     * @param array $request
     * @param array $assessmentIds
     * @return array 0: array A list of assessment data
     *               1: int[][] A list of IDs of students excluded from each
     *                  assessment, grouped by assessment ID
     *               2: array A list of summary links to assessments for
     *                  each student partaking
     *               3: array A summation of marks for each capability
     *
     * @throws Exception
     * @throws RouteNotFound
     */
    public function buildMarkbookData(?Folder\Model $folder, array $request, array $assessmentIds): array
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
        $studentMarks = [];

        /* If a folder wasn't provided, just accumulate users from the retrieve*Marks() methods; there isn't a mandatory
         * or conclusive list of guys to start with in such a scenario
         */
        if (null !== $folder) {
            $studentMarks = $this->insertFolderMembers($folder);
        }

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
        $overallMarks = $this->buildOverallMarks($assessments, $studentMarks, $exclusions, $folder, true);

        return [
            $assessments,
            $exclusions,
            $studentMarks,
            $overallMarks
        ];
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
                vr.assessment_id                               AS assessment_id,
                vr.assessment_grade_group_id                   AS assessment_grade_group_id,
                vr.assessment_title                            AS assessment_name,
                vr.assessment_max_mark                         AS assessment_max_mark,
                vr.assessment_mark_type                        AS assessment_mark_type,
                vr.assessment_grade_group_id                   AS assessment_grade_group_id,
                vr.assessment_weight                           AS assessment_weight,
                rubric_instance.id                             AS rubric_instance_id,
                rubric_instance.title                          AS rubric_instance_title,
                rubric_instance.rubric_capability_mark_type_id AS rubric_capability_mark_type_id,
                rubric_instance.weight                         AS rubric_instance_weight,
                vr.capability_id                               AS capability_id,
                vr.capability_name                             AS capability_name,
                vr.capability_max_value                        AS capability_max_value,
                vr.indicator_id                                AS indicator_id,
                vr.indicator_name                              AS indicator_name,
                vr.indicator_max_value                         AS indicator_max_value
            FROM v_rubric vr
            INNER JOIN rubric_instance ON vr.rubric_instance_id = rubric_instance.id
            INNER JOIN assessment a ON vr.assessment_id = a.id
            WHERE {$whereClause}
            ORDER BY
                a.due_date,
                a.title,
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
                    'title' => $row['rubric_instance_title'],
                    'weight' => $row['rubric_instance_weight'],
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
                // $capabilityMaxValue = $decimalHydrator->hydrate($row['capability_max_value']);

                $assessments[$row['assessment_id']]['gridData'][$row['rubric_instance_id']]['capabilities'][$row['capability_id']] = [
                    'id' => $row['capability_id'],
                    'name' => $row['capability_name'],
                    'max' => $decimalHydrator->hydrate($row['capability_max_value']),
                    /* "We care about this capability, even if we never show it to humans in the ui"
                     *
                     * We need this node to accumualte data even if we never intend to show it to humans in the ui.
                     * This .show node only has meaning to humans if Capability Mark Type was something other than None,
                     * and/or we chose it in the Filters settings.
                     */
                    'show' => $assessments[$row['assessment_id']]['gridData'][$row['rubric_instance_id']]['isMarkPerCapability'] && ($selectedCapabilities === null || in_array((int)$row['capability_id'], $selectedCapabilities, true)),
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
                        'max' => $decimalHydrator->hydrate($row['indicator_max_value']),
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
//        uasort(
//            $assessments,
//            static function (array $a, array $b): int {
//                return strnatcmp($a['name'], $b['name']);
//            }
//        );

        return $assessments;
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
                    vrm.assessment_id                              AS assessment_id,
                    vrm.student_id                                 AS student_id,
                    u.firstname                                    AS student_firstname,
                    u.preferred_name                               AS student_preferred_name,
                    u.lastname                                     AS student_lastname,
                    rubric_instance.id                             AS rubric_instance_id,
                    rubric_instance.title                          AS rubric_instance_title,
                    rubric_instance.rubric_capability_mark_type_id AS rubric_capability_mark_type_id,
                    rubric_instance.weight                         AS rubric_instance_weight,
                    vrm.capability_id                              AS capability_id,
                    vrm.capability_name                            AS capability_name,
                    vrm.capability_mark                            AS capability_mark,
                    vrm.capability_human_readable_mark             AS capability_human_readable_mark,
                    vrm.capability_max_value                       AS capability_max_value,
                    vrm.capability_mark_type_id                    AS capability_mark_type_id,
                    vrm.capability_grade_group_id                  AS capability_grade_group_id,
                    vrm.descriptor_name                            AS descriptor_name,
                    vrm.indicator_id                               AS indicator_id,
                    vrm.indicator_name                             AS indicator_name,
                    vrm.indicator_mark                             AS indicator_mark,
                    vrm.indicator_max_value                        AS indicator_max_value,
                    rl.name                                        AS level_name
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
                LEFT JOIN rubric_descriptor rd ON vrm.descriptor_id = rd.id AND rd.rubric_indicator_id = vrm.indicator_id
                LEFT JOIN rubric_level rl ON rd.level_id = rl.id
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
                    'title' => $row['rubric_instance_title'],
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
                    self::MARK_NODE_PUBLISHED => [
                        'normalisedMark' => $normalisedMark,
                        'humanReadableMark' => $humanReadableMark,
                    ],
                    /* Normalised and human-readable marks from the most recent feedback: these would typically be draft
                     * marks, but could end up being the same as the Published ones by sheer coincidence.
                     */
                    self::MARK_NODE_DRAFT => [
                        'normalisedMark' => null,
                        'humanReadableMark' => null,
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
                        self::MARK_NODE_PUBLISHED => [
                            'normalisedMark' => $normalisedMark,
                            'humanReadableMark' => $humanReadableMark,
                            'selectedLevel' => $row['level_name'],
                            'selectedDescriptorText' => $row['descriptor_name'],
                            'selectedDescriptorValue' => $row['indicator_mark'],
                        ],
                        /* Normalised and human-readable marks from the most recent feedback: these would typically be draft
                         * marks, but could end up being the same as the Published ones by sheer coincidence.
                         */
                        self::MARK_NODE_DRAFT => [
                            'normalisedMark' => null,
                            'humanReadableMark' => null,
                            'selectedLevel' => null,
                            'selectedDescriptorText' => null,
                            'selectedDescriptorValue' => null,
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
                    vrm.assessment_id                              AS assessment_id,
                    vrm.student_id                                 AS student_id,
                    u.firstname                                    AS student_firstname,
                    u.preferred_name                               AS student_preferred_name,
                    u.lastname                                     AS student_lastname,
                    rubric_instance.id                             AS rubric_instance_id,
                    rubric_instance.title                          AS rubric_instance_title,
                    rubric_instance.rubric_capability_mark_type_id AS rubric_capability_mark_type_id,
                    rubric_instance.weight                         AS rubric_instance_weight,
                    vrm.capability_id                              AS capability_id,
                    vrm.capability_name                            AS capability_name,
                    vrm.capability_mark                            AS capability_mark,
                    vrm.capability_human_readable_mark             AS capability_human_readable_mark,
                    vrm.capability_max_value                       AS capability_max_value,
                    vrm.capability_mark_type_id                    AS capability_mark_type_id,
                    vrm.capability_grade_group_id                  AS capability_grade_group_id,
                    vrm.descriptor_name                            AS descriptor_name,
                    vrm.indicator_id                               AS indicator_id,
                    vrm.indicator_name                             AS indicator_name,
                    vrm.indicator_mark                             AS indicator_mark,
                    vrm.indicator_max_value                        AS indicator_max_value,
                    rl.name                                        AS level_name,
                    vrm.feedback_date                              AS feedback_date
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
                LEFT JOIN rubric_descriptor rd ON vrm.descriptor_id = rd.id AND rd.rubric_indicator_id = vrm.indicator_id
                LEFT JOIN rubric_level rl ON rd.level_id = rl.id
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
                    'title' => $row['rubric_instance_title'],
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
                    self::MARK_NODE_PUBLISHED => [
                        'normalisedMark' => null,
                        'humanReadableMark' => null,
                        'selectedLevel' => null,
                        'selectedDescriptorText' => null,
                        'selectedDescriptorValue' => null,
                    ],
                    /* Normalised and human-readable marks from the most recent feedback: these would typically be draft
                     * marks, but could end up being the same as the Published ones by sheer coincidence.
                     */
                    self::MARK_NODE_DRAFT => [
                        'normalisedMark' => null,
                        'humanReadableMark' => null,
                        'selectedLevel' => null,
                        'selectedDescriptorText' => null,
                        'selectedDescriptorValue' => null,
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

            $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']][self::MARK_NODE_DRAFT] = [
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
                        self::MARK_NODE_PUBLISHED => [
                            'normalisedMark' => null,
                            'humanReadableMark' => null,
                            'selectedLevel' => null,
                            'selectedDescriptorText' => null,
                            'selectedDescriptorValue' => null,
                        ],
                        /* Normalised and human-readable marks from the most recent feedback: these would typically be draft
                         * marks, but could end up being the same as the Published ones by sheer coincidence.
                         */
                        self::MARK_NODE_DRAFT => [
                            'normalisedMark' => null,
                            'humanReadableMark' => null,
                            'selectedLevel' => null,
                            'selectedDescriptorText' => null,
                            'selectedDescriptorValue' => null,
                        ],
                    ];
                }

                $marks = $this->generateMarkTuple(
                    $this->decimalHydrator->hydrate($row['indicator_mark'] ?? null),
                    $this->decimalHydrator->hydrate($row['indicator_max_value'] ?? null),
                );

                $normalisedMark = $marks['normalisedMark'];
                $humanReadableMark = $marks['humanReadableMark'];

                $students[$row['student_id']]['gridData'][$row['assessment_id']][$row['rubric_instance_id']]['capabilities'][$row['capability_id']]['indicators'][$row['indicator_id']][self::MARK_NODE_DRAFT] = [
                    'normalisedMark' => $normalisedMark,
                    'humanReadableMark' => $humanReadableMark,
                    'selectedLevel' => $row['level_name'],
                    'selectedDescriptorText' => $row['descriptor_name'],
                    'selectedDescriptorValue' => $row['indicator_mark'],
                ];
            }
        }

        return $students;
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
     * @return array
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
     * Iterates student individual marks to return overall student marks for capabilities and indicators
     *
     * @param array $assessments
     * @param array $studentMarks
     * @param array $exclusions
     * @param Model|null $folder
     * @param bool $includeDiagnostics Adds the individual calculations as a frontend payload so you can see it in prod
     * @return array
     */
    private function buildOverallMarks(
        array $assessments,
        array $studentMarks,
        array $exclusions,
        ?Folder\Model $folder,
        bool $includeDiagnostics = false
    ): array {
        $overallMarks = $this->aggregateMarks($assessments, $studentMarks, $exclusions, $folder, $includeDiagnostics);

        foreach ($overallMarks as &$capability) {
            uasort($capability['indicators'], [$this, 'sortByName']);
        }

        unset($capability);

        uasort($overallMarks, [$this, 'sortByName']);

        return $overallMarks;
    }


    /**
     * Iterates individual student marks to build the weighted sums per capability and indicator
     *
     * @param array $assessments
     * @param array $studentMarks
     * @param array $exclusions
     * @param Model|null $folder
     * @param bool $includeDiagnostic
     * @return array
     */
    private function aggregateMarks(
        array $assessments,
        array $studentMarks,
        array $exclusions,
        ?Folder\Model $folder,
        bool $includeDiagnostic
    ): array {
        /* Non-iterable Coefficients Lookup
         *
         * Coefficients of a weighted sum algorithm are tabulated here, per student, for convenience.
         * This saves you having to constantly traverse data sets to get something for the WeightedAggregator formula.
         *
         * NOTE That this is intended as a HASH LOOKUP ONLY.
         * IT IS NOT intended for you to foreach() because of its intentional structure.
         *
         * I didn't really have the time or inclination to convert this to a tightly-typed struct with limited access.
         * The next evolution of this would be a defined object, Coefficients, which is immutable: pass $assessments
         * as a dependency, ->generate(), and only have read methods to a coordinate in the struct. This would make it
         * effectively readonly once generated.
         */

        /* $coefficients is a set of supposedly-convenient values that we can quickly reference
         *
        $coefficient['assessments'][$assessmentId]
          The weight of an individual assessment.

        $coefficients['grids'][$rubricInstanceId]
          The weight of a particular rubric grid.

        $coefficients['grids']['assessments'][$assessmentId]
          The total weight of the rubric grids in $assessmentId. This is used to calculate a weight sum of each rubric
          grid's contribution to the overall value.

        $coefficients['capabilities'][$capabilityId]['students'][$studentId]['totalAssessmentWeight'][self::MARK_NODE_PUBLISHED]
          The total weight of the capability's usages across all the assessments where the student had a mark.
          This aggregates toward a denominator for "assessment total weight" for this constituent of a rubric grid.
          It only accumulates if a student had a mark in it; this denominator is specifically for a particular guy
          There's a "published" and "draft" variants of this

        $coefficients['capabilities'][$capabilityId]['indicators'][$indicatorId]['students'][$studentId]['totalAssessmentWeight'][self::MARK_NODE_PUBLISHED]
          The total weight of the indicator's usages across all the assessments where the student had a mark.
          This aggregates toward a denominator for "assessment total weight" for this constituent of a rubric grid.
          It only accumulates if a student had a mark in it; this denominator is specifically for a particular guy
          There's a "published" and "draft" variants of this
        */
        $coefficients = [];

        /* Precalculate all the coefficients now that we have assembled $assessments in its entirety with all its
         * capabilities and indicators and their max_values.
         */
        $overallMarks = [];

        foreach ($assessments as $assessmentId => $assessmentData) {
            $coefficients['assessments'][$assessmentId] = $assessmentData['weight'];

            foreach ($assessmentData['gridData'] as $rubricInstanceId => $rubricGridData) {
                if (!isset($coefficients['grids']['assessments'][$assessmentId])) {
                    $coefficients['grids']['assessments'][$assessmentId] = [
                        'totalGridWeight' => 0,
                    ];
                }

                /* Total weight of all the grids in a particular assessment
                 */
                $coefficients['grids']['assessments'][$assessmentId]['totalGridWeight'] += $rubricGridData['weight'];
                $coefficients['grids'][$rubricInstanceId] = $rubricGridData['weight'] ? (float)$rubricGridData['weight'] : null;

                /* Set up OverallMarks struct
                 *
                 * This pre-compiles capability/indicator structs while we're traversing this $assessments struct.
                 * It used to be split out into its own traversal
                 */
                foreach ($rubricGridData['capabilities'] as $capabilityId => $capabilityData) {
                    if (!isset($overallMarks[$capabilityId])) {
                        $overallMarks[$capabilityId] = [
                            'name' => $capabilityData['name'],
                            /* What each student got for this capability
                             * Contains a "published" and "draft" node
                             */
                            'students' => [],
                            /* The indicators used in this capability
                             */
                            'indicators' => [],
                        ];
                    }

                    foreach ($capabilityData['indicators'] as $indicatorId => $indicatorData) {
                        if (!isset($overallMarks[$capabilityId]['indicators'][$indicatorId])) {
                            $overallMarks[$capabilityId]['indicators'][$indicatorId] = [
                                'name' => $indicatorData['name'],
                                /* What each student got for this indicator
                                 * Contains a "published" and "draft" node
                                 */
                                'students' => []
                            ];
                        }
                    }
                }
            }
        }

        /* Precompute denominators for each student's capabilities and indicators
         */
        foreach ($studentMarks as $studentId => $studentData) {
            foreach ($studentData['gridData'] as $assessmentId => $rubricInstanceData) {
                foreach ($rubricInstanceData as $rubricInstanceId => $rubricGridData) {
                    foreach ($rubricGridData['capabilities'] as $capabilityId => $capabilityData) {
                        foreach (self::MARK_NODES as $node) {
                            if (null !== $capabilityData[$node]['normalisedMark']) {
                                /* Initialise and add it to the published capability total for this guy
                                 */
                                if (!isset($coefficients['capabilities'][$capabilityId]['students'][$studentId]['totalAssessmentWeight'][$node])) {
                                    $coefficients['capabilities'][$capabilityId]['students'][$studentId]['totalAssessmentWeight'][$node] = 0;
                                }

                                $coefficients['capabilities'][$capabilityId]['students'][$studentId]['totalAssessmentWeight'][$node] += $coefficients['assessments'][$assessmentId];
                            }
                        }

                        foreach ($capabilityData['indicators'] as $indicatorId => $indicatorData) {
                            foreach (self::MARK_NODES as $node) {
                                if (null !== $indicatorData[$node]['normalisedMark']) {
                                    /* Initialise and add it to the published indicator total for this guy
                                     */
                                    if (!isset($coefficients['capabilities'][$capabilityId]['indicators'][$indicatorId]['students'][$studentId]['totalAssessmentWeight'][$node])) {
                                        $coefficients['capabilities'][$capabilityId]['indicators'][$indicatorId]['students'][$studentId]['totalAssessmentWeight'][$node] = 0;
                                    }

                                    $coefficients['capabilities'][$capabilityId]['indicators'][$indicatorId]['students'][$studentId]['totalAssessmentWeight'][$node] += $coefficients['assessments'][$assessmentId];
                                }
                            }
                        }
                    }
                }
            }
        }

        /* Accumulate the numerator and denominator for our formula. Sadly we need to do this first before we can
         * actually use the formula
         */
        foreach ($studentMarks as $studentId => $studentData) {
            foreach ($studentData['gridData'] as $assessmentId => $rubricInstanceData) {
                foreach ($rubricInstanceData as $rubricInstanceId => $rubricGridData) {
                    foreach ($rubricGridData['capabilities'] as $capabilityId => $capabilityData) {
                        /* Excluded guys get null for this calculation
                         */
                        if (!isset($overallMarks[$capabilityId]['students'][$studentId])) {
                            $overallMarks[$capabilityId]['students'][$studentId] = [
                                self::MARK_NODE_PUBLISHED => [
                                    'normalisedMark' => null,
                                    'humanReadableMark' => null,
                                    'formula' => [
                                        'numerator' => null,
                                        'denominator' => null,
                                    ],
                                    'reasoning' => [],

                                ],
                                self::MARK_NODE_DRAFT => [
                                    'normalisedMark' => null,
                                    'humanReadableMark' => null,
                                    'formula' => [
                                        'numerator' => null,
                                        'denominator' => null,
                                    ],
                                    'reasoning' => [],
                                ],
                            ];
                        }

                        if (!isset($exclusions[$assessmentId][$studentId])) {
                            foreach (self::MARK_NODES as $node) {
                                /* Jez Math Pieces
                                 */
                                $assessmentWeight = $coefficients['capabilities'][$capabilityId]['students'][$studentId]['totalAssessmentWeight'][$node] ?? null;

                                /* Skip if this is null; it doesn't count toward their total
                                 */
                                $normalisedMark = $capabilityData[$node]['normalisedMark'];

                                if (null !== $assessmentWeight && null !== $normalisedMark) {
                                    $gridWeight = $coefficients['grids']['assessments'][$assessmentId]['totalGridWeight'];
                                    $effectiveWeight = $assessmentWeight * $gridWeight;
                                    $numerator = $normalisedMark * $effectiveWeight;

                                    /* Jez Maths
                                     */
                                    $overallMarks[$capabilityId]['students'][$studentId][$node]['formula']['numerator'] += $numerator;
                                    $overallMarks[$capabilityId]['students'][$studentId][$node]['formula']['denominator'] += $effectiveWeight;

                                    /* Jez reasoning
                                     */
                                    $overallMarks[$capabilityId]['students'][$studentId][$node]['reasoning'][] = sprintf(
                                        'assessmentId: %s, assessmentWeight: %s, gridWeight: %s, numerator: %s, denominator: %s',
                                        $assessmentId,
                                        $assessmentWeight,
                                        $gridWeight,
                                        $numerator,
                                        $effectiveWeight,
                                    );

                                    $overallMarks[$capabilityId]['students'][$studentId][$node]['reasoning'][] = sprintf(
                                        'numerator += %s * %s, denominator += %s * %s',
                                        $normalisedMark,
                                        $effectiveWeight,
                                        $assessmentWeight,
                                        $gridWeight,
                                    );

                                    $overallMarks[$capabilityId]['students'][$studentId][$node]['reasoning'][] = null;
                                }
                            }
                        }

                        /* Now dig into each indicator within this capability and aggregate their values in a very
                         * similar fashion.
                         */
                        foreach ($capabilityData['indicators'] as $indicatorId => $indicatorData) {
                            /* Excluded guys get null for this calculation
                             */
                            if (!isset($overallMarks[$capabilityId]['indicators'][$indicatorId]['students'][$studentId])) {
                                $overallMarks[$capabilityId]['indicators'][$indicatorId]['students'][$studentId] = [
                                    self::MARK_NODE_PUBLISHED => [
                                        'normalisedMark' => null,
                                        'humanReadableMark' => null,
                                        'formula' => [
                                            'numerator' => null,
                                            'denominator' => null,
                                        ],
                                        'reasoning' => [],
                                    ],
                                    self::MARK_NODE_DRAFT => [
                                        'normalisedMark' => null,
                                        'humanReadableMark' => null,
                                        'formula' => [
                                            'numerator' => null,
                                            'denominator' => null,
                                        ],
                                        'reasoning' => [],
                                    ],
                                ];
                            }

                            if (!isset($exclusions[$assessmentId][$studentId])) {
                                foreach (self::MARK_NODES as $node) {
                                    /* Jez Math Pieces
                                     */
                                    $assessmentWeight = $coefficients['capabilities'][$capabilityId]['indicators'][$indicatorId]['students'][$studentId]['totalAssessmentWeight'][$node] ?? null;

                                    /* Skip if this is null; it doesn't count toward their total
                                     */
                                    $normalisedMark = $capabilityData['indicators'][$indicatorId][$node]['normalisedMark'];

                                    if (null !== $assessmentWeight && null !== $normalisedMark) {
                                        $gridWeight = $coefficients['grids']['assessments'][$assessmentId]['totalGridWeight'];
                                        $effectiveWeight = $assessmentWeight * $gridWeight;
                                        $numerator = $normalisedMark * $effectiveWeight;

                                        /* Jez Maths
                                         */
                                        $overallMarks[$capabilityId]['indicators'][$indicatorId]['students'][$studentId][$node]['formula']['numerator'] += $numerator;
                                        $overallMarks[$capabilityId]['indicators'][$indicatorId]['students'][$studentId][$node]['formula']['denominator'] += $effectiveWeight;

                                        /* Jez reasoning
                                         */
                                        $overallMarks[$capabilityId]['indicators'][$indicatorId]['students'][$studentId][$node]['reasoning'][] = sprintf(
                                            'assessmentId: %s, assessmentWeight: %s, gridWeight: %s, numerator: %s, denominator: %s',
                                            $assessmentId,
                                            $assessmentWeight,
                                            $gridWeight,
                                            $numerator,
                                            $effectiveWeight,
                                        );

                                        $overallMarks[$capabilityId]['indicators'][$indicatorId]['students'][$studentId][$node]['reasoning'][] = sprintf(
                                            'numerator += %s * %s, denominator += %s * %s',
                                            $normalisedMark,
                                            $effectiveWeight,
                                            $assessmentWeight,
                                            $gridWeight,
                                        );

                                        $overallMarks[$capabilityId]['indicators'][$indicatorId]['students'][$studentId][$node]['reasoning'][] = null;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        /* Now that we accumulated everything, go back and calculate normalised and human-readable marks
         */

        /* Now precompute overall marks for each student.
         *
         * This is something of a dirty secret but the normalised mark we compute for each capability and indicator are
         * converted into a Letter Grade equivalent for human-readable values. These are defined in admin via the Year
         * Level lists: there's a doodad that lets you choose a grade group to for Overall Grade values.
         *
         * It just so happens that this Letter Grade equivalent might actually be a number which is why, at first glance,
         * the rubric markbook data might "look wrong" but actually isn't.
         *
         * This is a massive configuration at a distance and totally not obvious lol unless you're an actual teacher...
         */
        $aggregator = new WeightedAggregator();

        /**
         * Logic for the mark scheme...
         *
         * If there is no year level on the class page, we should default back to the system default.
         * If there are multiple year levels on the class page, and they agree (we should use their grade group)
         * If there are multiple year levels on the class page, and they do NOT agree (all different grade groups), we should fall back to the system default grade group.
         */
        if (null !== $folder) {
            $markScheme = $this->markSchemeFactory->spawnForFolder($folder, true);
        } else {
            /* Presume this when $folder wasn't specified
             */
            $markScheme = new Percentage();
        }

        foreach ($overallMarks as $capabilityId => &$capabilityData) {
            foreach ($capabilityData['students'] as $studentId => &$studentCapabilityData) {
                foreach (self::MARK_NODES as $node) {
                    $val = $aggregator->calculate(
                        $studentCapabilityData[$node]['formula']['numerator'],
                        $studentCapabilityData[$node]['formula']['denominator'],
                        null
                    );

                    if (null !== $val) {
                        $studentCapabilityData[$node]['normalisedMark'] = $val['val'];
                        $studentCapabilityData[$node]['humanReadableMark'] = $markScheme->getHumanReadableMark((float)$val['val']);
                        $studentCapabilityData[$node]['reasoning'][] = $val['diagnostic'];
                    }
                }
            }

            foreach ($capabilityData['indicators'] as $indicatorId => &$indicatorData) {
                foreach ($indicatorData['students'] as $studentId => &$studentIndicatorData) {
                    foreach (self::MARK_NODES as $node) {
                        $val = $aggregator->calculate(
                            $studentIndicatorData[$node]['formula']['numerator'],
                            $studentIndicatorData[$node]['formula']['denominator'],
                            null
                        );

                        if (null !== $val) {
                            $studentIndicatorData[$node]['normalisedMark'] = $val['val'];
                            $studentIndicatorData[$node]['humanReadableMark'] = $markScheme->getHumanReadableMark((float)$val['val']);
                            $studentIndicatorData[$node]['reasoning'][] = $val['diagnostic'];
                        }
                    }
                }
            }
        }

        unset($capabilityData, $studentCapabilityData, $indicatorData, $studentIndicatorData);

        return $overallMarks;
    }


    /**
     * Quick alpha sort function for indicators and capabilities summary section
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    private function sortByName(array $a, array $b): int
    {
        return strnatcasecmp($a['name'], $b['name']);
    }
}
