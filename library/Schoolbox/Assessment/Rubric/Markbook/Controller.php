<?php
/**
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Markbook;

use Alaress\Web\Controller\Base;
use function array_key_exists;
use function array_keys;
use function array_merge;
use Aura\Di\Exception\ServiceNotFound;
use function count;
use function func_get_args;
use function implode;
use function in_array;
use LogicException;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Alaress\Database\Connector\IConnector;
use Schoolbox\Column\Boolean;
use Schoolbox\Column\Decimal;
use Schoolbox\Learning\MarkScheme;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Rubric\Grid\CapabilityUsage;
use Schoolbox\Assessment\Rubric\RowSet\Capability;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group as GradeGroup;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Alaress\Database;
use InvalidArgumentException;
use PDOException;
use Schoolbox\Mapper;
use function strnatcmp;
use function uasort;
use UnderflowException;
use Schoolbox\Filter;
use Aura\Router;
use DomainException;
use UnexpectedValueException;
use Schoolbox\Filter\Filters\Assessment\RubricUsage;

class Controller extends Base
{

    /**
     * @var IConnector
     */
    private $internalDb;

    /**
     * @var GradeGroup\Mapper
     */
    private $gradeGroupMapper;

    /**
     * Data source for group lists
     *
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /** @var Filter\Filters\Assessment\FactoryFactory */
    private $filterFactoryFactory;

    /** @var Filter\Selection\Mapper */
    private $filterSelectionMapper;

    /** @var Filter\Filters\Assessment\IdFinder */
    private $assessmentIdFinder;

    /** @var Filter\Mapper */
    private $filterMapper;


    /**
     * Constructor
     *
     * @param IConnector                               $internalDb
     * @param GradeGroup\Mapper                        $gradeGroupMapper
     * @param UserAccessMapper                         $userAccessMapper
     * @param Filter\Filters\Assessment\FactoryFactory $filterFactoryFactory
     * @param Filter\Selection\Mapper                  $filterSelectionMapper
     * @param Filter\Filters\Assessment\IdFinder       $assessmentIdFinder
     * @param Filter\Mapper                            $filterMapper
     */
    public function __construct(
        IConnector $internalDb,
        GradeGroup\Mapper $gradeGroupMapper,
        UserAccessMapper $userAccessMapper,
        Filter\Filters\Assessment\FactoryFactory $filterFactoryFactory,
        Filter\Selection\Mapper $filterSelectionMapper,
        Filter\Filters\Assessment\IdFinder $assessmentIdFinder,
        Filter\Mapper $filterMapper
    ) {
        $this->internalDb            = $internalDb;
        $this->gradeGroupMapper      = $gradeGroupMapper;
        $this->userAccessMapper      = $userAccessMapper;
        $this->filterFactoryFactory  = $filterFactoryFactory;
        $this->filterSelectionMapper = $filterSelectionMapper;
        $this->assessmentIdFinder    = $assessmentIdFinder;
        $this->filterMapper          = $filterMapper;
    }


    /**
     * Renders a filter panel and placeholder that will asynchronously load the rubric report
     *
     * @api   GET /learning/rubricMarkbook/{folderId}
     * @level 190
     * @param Folder\Model $folder Folder for this class
     * @throws Database\Predicate\InvalidColumnException
     * @throws InvalidArgumentException
     * @throws Mapper\DefinitionException
     * @throws Mapper\Exception
     * @throws Router\Exception\RouteNotFound
     * @throws ServiceNotFound If unable to locate HTML web Output
     * @throws UnderflowException
     * @throws DomainException
     * @throws UnexpectedValueException
     */
    public function getMarkbook(Folder\Model $folder): void
    {
        $queryArgs = $this->getRequest()->query->getArrayCopy();
        unset($queryArgs['page']);

        $page        = Filter\Page\Model::PAGE_RUBRIC_MARK_BOOK;
        $route       = $this->getRouter()->generate('rubricMarkbook.postMarkbook', ['folderId' => $folder->getId()]);
        $filterPanel = $this->filterFactoryFactory->buildPanel($this->getUser(), $page, $route, $this->getUser(), func_get_args(), $queryArgs);

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@rubricMarkbook/markbookOuter.twig',
                [
                    'folder'      => $folder,
                    'filterPanel' => $filterPanel,
                    'page'        => $page,
                ]
            )->setTab('submission')->setTitle('Rubric Markbook for ' . $folder->getName())
        );
    }


    /**
     * Displays a list of rubric marks for students and learners associated with the specified folder
     *
     * @api   POST /learning/rubricMarkbook/{folderId}
     * @level 190
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
     */
    public function postMarkbook(Folder\Model $folder): void
    {
        $request       = $this->request->post['filter_selection'] ?: [];
        $assessmentIds = $this->getAssessmentIds($folder->getId(), $request);
        $isFiltered    = (new Filter\RequestChecker())->isFilteredRequest($request);
        [$selectedCapabilities, $selectedIndicators] = $this->getRubricUsageSelections($request);
        $assessments   = $this->buildAssessmentData($assessmentIds, $selectedCapabilities, $selectedIndicators);
        $databaseMarks = $this->retrieveDatabaseMarks($assessments, $selectedCapabilities, $selectedIndicators);
        $derivedMarks  = $this->generateHumanReadableMarks($databaseMarks, $assessments);
        $studentMarks  = $this->insertFolderMembers($derivedMarks, $folder);
        $exclusions    = $this->getExclusions($assessmentIds);
        $linkedMarks   = $this->insertSummaryPageLinks($studentMarks, $assessmentIds, $exclusions);
        $overallMarks  = $this->buildOverallMarks($assessments, $linkedMarks);

        $this->getResponse()->content->set($this->buildJsonOutput([
            'status'  => 200,
            'content' => $this->buildUnthemedHtmlOutput(
                '@rubricMarkbook/markbookInner.twig',
                [
                    'isFiltered'  => $isFiltered,
                    'students'    => $linkedMarks,
                    'assessments' => $assessments,
                    'exclusions'  => $exclusions,
                ]
            )->getAsString(),
        ]));
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
        $rubricUsageSelections = $request[$rubricUsageFilter->getId()] ?? [];

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
        $codec               = $this->filterFactoryFactory->buildCodec();
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

        return $this->assessmentIdFinder->findIdsByFilters($filters);
    }


    /**
     * Fetch assessment data, and return it indexed by assessment ID
     *
     * @param int[] $assessmentIds
     * @param array $selectedCapabilities
     * @param array $selectedIndicators
     * @return array
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws Router\Exception\RouteNotFound
     * @throws UnderflowException
     */
    private function buildAssessmentData(array $assessmentIds, array $selectedCapabilities, array $selectedIndicators): array
    {
        $assessments = [];
        if (!count($assessmentIds) || (!count($selectedCapabilities) && !count($selectedIndicators))) {
            return $assessments;
        }

        $pb = (new Database\Predicate\Builder($this->internalDb, ''))->in('vr.assessment_id', $assessmentIds);

        $usagePb = new Database\Predicate\Builder($this->internalDb, '');
        if (count($selectedCapabilities)) {
            $usagePb->in('vr.capability_id', $selectedCapabilities);
        }
        if (count($selectedIndicators)) {
            $usagePb->in('vr.indicator_id', $selectedIndicators);
        }
        $pb->any($usagePb);
        $whereClause = implode(' AND ', $pb->getWhere());

        $sql  = "
            SELECT
                vr.assessment_id                  AS assessment_id,
                vr.assessment_grade_group_id      AS assessment_grade_group_id,
                vr.assessment_title               AS assessment_name,
                vr.assessment_max_mark            AS assessment_max_mark,
                vr.assessment_mark_type           AS assessment_mark_type,
                vr.assessment_grade_group_id      AS assessment_grade_group_id,
                vr.assessment_mark_per_capability AS assessment_rubric_group_marks,
                vr.capability_id                  AS capability_id,
                vr.capability_name                AS capability_name,
                vr.indicator_id                   AS indicator_id,
                vr.indicator_name                 AS indicator_name,
                vr.indicator_max_value            AS indicator_max_value
            FROM v_rubric vr
            WHERE {$whereClause}
            ORDER BY
                vr.assessment_id,
                vr.capability_sequence ASC,
                vr.capability_id,
                vr.indicator_sequence ASC,
                vr.indicator_id
        ";
        $stmt = $this->internalDb->prepareQuery($sql);

        $capabilities = [];
        $indicators   = [];

        $lastObservedAssessmentId = null;
        $lastObservedCapabilityId = null;

        foreach ($stmt->getIterator($pb->getParams()) as $row) {
            if ($lastObservedAssessmentId !== $row['assessment_id']) {
                // Has the assessment we're looking at changed? Reset the capabilities
                $capabilities = [];
            }

            if ($lastObservedCapabilityId !== $row['capability_id'] || $lastObservedAssessmentId !== $row['assessment_id']) {
                // Has the capability we're looking at changed? Reset the indicators
                $indicators = [];
            }

            if (in_array((int)$row['indicator_id'], $selectedIndicators, true)) {
                $indicators[$row['indicator_id']] = [
                    'name' => $row['indicator_name'],
                ];
            }

            $capabilities[$row['capability_id']] = [
                'name'       => $row['capability_name'],
                'indicators' => $indicators,
                'show'       => in_array((int)$row['capability_id'], $selectedCapabilities, true),
            ];


            // Stuff everything about the assessment into an array
            $assessments[$row['assessment_id']] = [
                'name'           => $row['assessment_name'],
                'statusLink'     => $this->getRouter()->generate('learningAssessment.getStatus', ['assessmentId' => $row['assessment_id']]),
                'maxMark'        => $row['assessment_max_mark'],
                'markType'       => $row['assessment_mark_type'],
                'gradeGroupId'   => $row['assessment_grade_group_id'],
                'usesMarkPerCap' => $row['assessment_rubric_group_marks'],
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
     * @param int[] $assessments
     * @param array $selectedCapabilities
     * @param array $selectedIndicators
     * @return array
     * @throws Database\Exception
     * @throws Database\Predicate\InvalidColumnException
     * @throws UnderflowException
     */
    private function retrieveDatabaseMarks(array $assessments, array $selectedCapabilities, array $selectedIndicators): array
    {
        $students = [];
        if (!count($assessments) || (!count($selectedCapabilities) && !count($selectedIndicators))) {
            return $students;
        }

        $innerPb    = (new Database\Predicate\Builder($this->internalDb, ''))
            ->isNull('sr.deleted_at')
            ->notNull('sr.published_at')
            ->equals('sr.review_route', ReturnedWork::REVIEW_ROUTE_INSTRUCTOR);
        $innerWhere = implode(' AND ', $innerPb->getWhere());

        $outerPb = (new Database\Predicate\Builder($this->internalDb, ''))
            ->equals('vrm.feedback_type', ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->in('vrm.assessment_id', array_keys($assessments));

        $usagePb = new Database\Predicate\Builder($this->internalDb, '');
        if (count($selectedCapabilities)) {
            $usagePb->in('vrm.capability_id', $selectedCapabilities);
        }
        if (count($selectedIndicators)) {
            $usagePb->in('vrm.indicator_id', $selectedIndicators);
        }
        $outerPb->any($usagePb);
        $outerWhere = implode(' AND ', $outerPb->getWhere());

        $getAuthoritativeMarksQuery = $this->internalDb->prepareQuery("
            SELECT
                vrm.assessment_id        AS assessment_id,
                vrm.student_id           AS student_id,
                u.firstname              AS student_firstname,
                u.lastname               AS student_lastname,
                vrm.capability_id        AS capability_id,
                vrm.capability_mark      AS capability_mark,
                vrm.capability_max_value AS capability_max_value,
                vrm.descriptor_name      AS descriptor_name,
                vrm.indicator_id         AS indicator_id,
                vrm.indicator_mark       AS indicator_mark,
                vrm.indicator_max_value  AS indicator_max_value
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
            INNER JOIN user u ON vrm.student_id = u.id
            WHERE {$outerWhere}
            ORDER BY vrm.student_id, vrm.assessment_id, vrm.capability_id, vrm.indicator_id
            ");

        $params = array_merge($innerPb->getParams(), $outerPb->getParams());

        $assessmentsForStudent = [];
        $capabilityMarks       = [];
        $indicatorMarks        = [];

        $lastObservedAssessmentId = null;
        $lastObservedCapabilityId = null;
        $lastObservedStudentId    = null;

        foreach ($getAuthoritativeMarksQuery->getIterator($params) as $i => $row) {
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

            if (in_array((int)$row['indicator_id'], $selectedIndicators, true)) {
                $indicatorMarks[$row['indicator_id']] = [
                    'databaseMark'           => $row['indicator_mark'],
                    'maxMark'                => $row['indicator_max_value'],
                    'selectedDescriptorText' => $row['descriptor_name'],
                ];
            }

            $capData = [
                'indicators' => $indicatorMarks,
            ];
            if ($assessments[$row['assessment_id']]['capabilities'][$row['capability_id']]['show']) {
                $capData['databaseMark'] = $row['capability_mark'];
                $capData['maxMark']      = $row['capability_max_value'];
            }
            $capabilityMarks[$row['capability_id']] = $capData;

            $assessmentsForStudent[$row['assessment_id']] = [
                'capabilities' => $capabilityMarks,
            ];

            // Stuff everything about the students marks into an array
            $students[$row['student_id']] = [
                'firstname'   => $row['student_firstname'],
                'lastname'    => $row['student_lastname'],
                'assessments' => $assessmentsForStudent,
                'profileLink' => '/search/user/' . $row['student_id'],
            ];

            $lastObservedAssessmentId = $row['assessment_id'];
            $lastObservedCapabilityId = $row['capability_id'];
            $lastObservedStudentId    = $row['student_id'];
        }
        return $students;
    }


    /**
     * Work out the human readable marks from the raw marks and insert them into the student marks array
     *
     * @param array $students
     * @param array $assessments
     * @return array
     * @throws /Aura/Router/Exception/RouteNotFound
     */
    private function generateHumanReadableMarks(array $students, array $assessments): array
    {
        $decimalHydrator   = new Decimal();
        $booleanHydrator   = new Boolean();
        $markSchemeFactory = new MarkScheme\Factory();
        $totalMarkScheme   = new MarkScheme\TotalMarkFractional();

        foreach ($students as $studentId => $student) {
            foreach($student['assessments'] as $assessmentId => $assessmentMarks) {

                $assessmentMaxMark        = $decimalHydrator->hydrate($assessments[$assessmentId]['maxMark']);
                $assessmentMarkType       = $assessments[$assessmentId]['markType'];
                $assessmentUsesMarkPerCap = $booleanHydrator->hydrate($assessments[$assessmentId]['usesMarkPerCap']);

                // Create some things the markscheme factory wants our Due Work to have
                try {
                    $gradeGroup = $this->gradeGroupMapper->findById($assessments[$assessmentId]['gradeGroupId']);
                } catch (Mapper\Exception $e) {
                    $gradeGroup = null;
                }

                $markType = new MarkType();
                $markType->setId($assessmentMarkType);

                // Fill out a Due Work with the data we need for the markscheme factory to work
                $stubAssessment = new DueWork\Model();
                $stubAssessment->setId($assessmentId);
                $stubAssessment->setMarkPerCapability($assessmentUsesMarkPerCap);
                $stubAssessment->setMaxMark($assessmentMaxMark);
                $stubAssessment->setMarkType($markType);
                if ($gradeGroup !== null) {
                    $stubAssessment->setGradeGroup($gradeGroup);
                }

                foreach($assessmentMarks['capabilities'] as $capabilityId => $capabilityMarks) {
                    $capabilityDatabaseMark = $decimalHydrator->hydrate($capabilityMarks['databaseMark']);

                    if ($capabilityDatabaseMark !== null) {
                        $capabilityMaxMark = $decimalHydrator->hydrate($capabilityMarks['maxMark']);

                        $capabilityUsageModel = new CapabilityUsage(new Capability());
                        $capabilityUsageModel->setMaxValue($capabilityMaxMark);

                        $capabilityMarkScheme = $markSchemeFactory->spawnForCapability($stubAssessment, $capabilityMaxMark);
                        $capabilityMarkModel = new CapabilityMark($capabilityUsageModel, $capabilityMarkScheme);
                        $capabilityMarkModel->setValue($capabilityDatabaseMark);

                        $humanReadableCapabilityMark = $capabilityMarkModel->retrieveHumanReadableMark();
                        $normalisedCapabilityMark = $capabilityMarkModel->retrieveNormalizedMark();
                        $students[$studentId]['assessments'][$assessmentId]['capabilities'][$capabilityId]['humanReadableMark'] = $humanReadableCapabilityMark;
                        $students[$studentId]['assessments'][$assessmentId]['capabilities'][$capabilityId]['normalisedMark'] = $normalisedCapabilityMark;
                    } else {
                        $students[$studentId]['assessments'][$assessmentId]['capabilities'][$capabilityId]['humanReadableMark'] = null;
                        $students[$studentId]['assessments'][$assessmentId]['capabilities'][$capabilityId]['normalisedMark'] = null;
                    }

                    foreach ($capabilityMarks['indicators'] as $indicatorId => $indicatorMarks) {
                        $indicatorMaxMark           = $decimalHydrator->hydrate($indicatorMarks['maxMark']);
                        $indicatorDatabaseMark      = $decimalHydrator->hydrate($indicatorMarks['databaseMark']);
                        $humanReadableIndicatorMark = null;
                        $normalisedIndicatorMark    = null;

                        if ($indicatorMaxMark) {
                            $normalisedIndicatorMark    = $indicatorDatabaseMark !== null
                                ? ($indicatorDatabaseMark / $indicatorMaxMark) * 100
                                : null;
                            $humanReadableIndicatorMark = $totalMarkScheme->setTotalMark($indicatorMaxMark)->getHumanReadableMark($normalisedIndicatorMark);
                        }

                        $students[$studentId]['assessments'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['humanReadableMark'] = $humanReadableIndicatorMark;
                        $students[$studentId]['assessments'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['normalisedMark'] = $normalisedIndicatorMark;
                    }
                }
            }
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
                return strnatcmp($a['lastname'], $b['lastname']);
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
                $summaryLink = $this->getRouter()->generate(
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

    private function buildOverallMarks(array $assessments, array $linkedMarks): array
    {
        return [];
    }
}
