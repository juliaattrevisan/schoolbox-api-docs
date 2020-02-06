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
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router;
use DomainException;
use InvalidArgumentException;
use LogicException;
use PDOException;
use RangeException;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group as GradeGroup;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Rubric\Grid\CapabilityUsage;
use Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark;
use Schoolbox\Assessment\Rubric\RowSet\Capability;
use Schoolbox\Column\Boolean;
use Schoolbox\Column\Decimal;
use Schoolbox\Column\Integer;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\Filter\Filters\Assessment\RubricUsage;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\MarkScheme;
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
     * @var GradeGroup\Mapper
     */
    private $gradeGroupMapper;

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
     * Constructor
     *
     * @param IConnector                         $internalDb
     * @param GradeGroup\Mapper                  $gradeGroupMapper
     * @param UserAccessMapper                   $userAccessMapper
     * @param Filter\IFactoryFactory             $filterFactoryFactory
     * @param Filter\Selection\Mapper            $filterSelectionMapper
     * @param Filter\Filters\Assessment\IdFinder $assessmentIdFinder
     * @param Filter\Mapper                      $filterMapper
     */
    public function __construct(
        IConnector $internalDb,
        GradeGroup\Mapper $gradeGroupMapper,
        UserAccessMapper $userAccessMapper,
        Filter\IFactoryFactory $filterFactoryFactory,
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
     * @since 190
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
                ]
            )->setTab('submission')->setTitle('Rubric Markbook for ' . $folder->getName())
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
        $request       = $this->request->post['filter_selection'] ?: [];
        $assessmentIds = $this->getAssessmentIds($folder->getId(), $request);

        [
            $assessments,
            $exclusions,
            $students,
            $overallMarks
        ] = $this->buildMarkbookData($folder, $request, $assessmentIds);

        $csv = new Export\Csv($students, $assessments, $exclusions, $overallMarks);
        $csv->export('Markbook.csv');
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
        $request       = $this->request->post['filter_selection'] ?: [];
        $assessmentIds = $this->getAssessmentIds($folder->getId(), $request);
        $codec         = new Codec($this->filterMapper, $this->filterSelectionMapper);

        [
            $assessments,
            $exclusions,
            $students,
            $overallMarks
        ] = $this->buildMarkbookData($folder, $request, $assessmentIds);

        $this->getResponse()->content->set($this->buildJsonOutput([
            'status'  => 200,
            'content' => $this->buildUnthemedHtmlOutput(
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

        return $this->assessmentIdFinder->findIdsByFilters($filters);
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

        $pb = (new Database\Predicate\Builder($this->internalDb, ''))->in('vr.assessment_id', $assessmentIds);

        if ($filteringByRubricUsage) {
            $usagePb = new Database\Predicate\Builder($this->internalDb, '');
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
                vr.assessment_id                  AS assessment_id,
                vr.assessment_grade_group_id      AS assessment_grade_group_id,
                vr.assessment_title               AS assessment_name,
                vr.assessment_max_mark            AS assessment_max_mark,
                vr.assessment_mark_type           AS assessment_mark_type,
                vr.assessment_grade_group_id      AS assessment_grade_group_id,
                vr.assessment_mark_per_capability AS assessment_rubric_group_marks,
                vr.assessment_weight              AS assessment_weight,
                vr.capability_id                  AS capability_id,
                vr.capability_name                AS capability_name,
                vr.capability_max_value           AS capability_max_value,
                vr.indicator_id                   AS indicator_id,
                vr.indicator_name                 AS indicator_name,
                vr.indicator_max_value            AS indicator_max_value
            FROM v_rubric vr
            WHERE {$whereClause}
            ORDER BY
                vr.assessment_id,
                vr.capability_sequence,
                vr.capability_id,
                vr.indicator_sequence,
                vr.indicator_id
        ";
        $stmt = $this->internalDb->prepareQuery($sql);

        $capabilities = [];
        $indicators   = [];

        $lastObservedAssessmentId = null;
        $lastObservedCapabilityId = null;
        $decimalHydrator          = new Decimal();
        $integerHydrator          = new Integer();

        foreach ($stmt->getIterator($pb->getParams()) as $row) {
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
                'name'       => $row['capability_name'],
                'indicators' => $indicators,
                'show'       => $capabilityMaxValue && ($selectedCapabilities === null || in_array((int)$row['capability_id'], $selectedCapabilities, true)),
            ];

            // Stuff everything about the assessment into an array
            $assessments[$row['assessment_id']] = [
                'name'           => $row['assessment_name'],
                'statusLink'     => $this->getRouter()->generate('learningAssessment.getStatus', ['assessmentId' => $row['assessment_id']]),
                'maxMark'        => $row['assessment_max_mark'],
                'markType'       => $row['assessment_mark_type'],
                'gradeGroupId'   => $row['assessment_grade_group_id'],
                'usesMarkPerCap' => $row['assessment_rubric_group_marks'],
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
     * @param int[]      $assessments
     * @param null|array $selectedCapabilities Null if rubricUsage filter not enabled for page
     * @param null|array $selectedIndicators   Null if rubricUsage filter not enabled for page
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

        $innerPb    = (new Database\Predicate\Builder($this->internalDb, ''))
            ->isNull('sr.deleted_at')
            ->notNull('sr.published_at')
            ->equals('sr.review_route', ReturnedWork::REVIEW_ROUTE_INSTRUCTOR);
        $innerWhere = implode(' AND ', $innerPb->getWhere());

        $outerPb = (new Database\Predicate\Builder($this->internalDb, ''))
            ->equals('vrm.feedback_type', ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->in('vrm.assessment_id', array_keys($assessments));

        if ($filteringByRubricUsage) {
            $usagePb = new Database\Predicate\Builder($this->internalDb, '');
            if (is_array($selectedCapabilities) && count($selectedCapabilities)) {
                $usagePb->in('vrm.capability_id', $selectedCapabilities);
            }
            if (is_array($selectedCapabilities) && count($selectedIndicators)) {
                $usagePb->in('vrm.indicator_id', $selectedIndicators);
            }
            $outerPb->any($usagePb);
        }

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
            if ($assessments[$row['assessment_id']]['capabilities'][$row['capability_id']]['show']) {
                $capData['databaseMark'] = $assessments[$row['assessment_id']]['usesMarkPerCap'] ? $row['capability_mark'] : null;
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
            foreach ($student['assessments'] as $assessmentId => $assessmentMarks) {

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

                foreach ($assessmentMarks['capabilities'] as $capabilityId => $capabilityMarks) {
                    $capabilityDatabaseMark = $decimalHydrator->hydrate($capabilityMarks['databaseMark']);

                    if ($capabilityDatabaseMark !== null) {
                        $capabilityMaxMark = $decimalHydrator->hydrate($capabilityMarks['maxMark']);

                        $capabilityUsageModel = new CapabilityUsage(new Capability());
                        $capabilityUsageModel->setMaxValue($capabilityMaxMark);

                        $capabilityMarkScheme = $markSchemeFactory->spawnForCapability($stubAssessment, $capabilityMaxMark);
                        $capabilityMarkModel  = new CapabilityMark($capabilityUsageModel, $capabilityMarkScheme);
                        $capabilityMarkModel->setValue($capabilityDatabaseMark);

                        $humanReadableCapabilityMark                                                                            = $capabilityMarkModel->retrieveHumanReadableMark();
                        $normalisedCapabilityMark                                                                               = $capabilityMarkModel->retrieveNormalizedMark();
                        $students[$studentId]['assessments'][$assessmentId]['capabilities'][$capabilityId]['humanReadableMark'] = $humanReadableCapabilityMark;
                        $students[$studentId]['assessments'][$assessmentId]['capabilities'][$capabilityId]['normalisedMark']    = $normalisedCapabilityMark;
                    } else {
                        $students[$studentId]['assessments'][$assessmentId]['capabilities'][$capabilityId]['humanReadableMark'] = null;
                        $students[$studentId]['assessments'][$assessmentId]['capabilities'][$capabilityId]['normalisedMark']    = null;
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
                        $students[$studentId]['assessments'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['normalisedMark']    = $normalisedIndicatorMark;
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
                $last = strnatcasecmp($a['lastname'], $b['lastname']);
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


    /**
     * Iterates student individual marks to return overall student marks for capabilities and indicators
     *
     * @param array $assessments
     * @param array $linkedMarks
     * @return array
     * @throws InvalidArgumentException
     * @throws RangeException
     */
    private function buildOverallMarks(array $assessments, array $linkedMarks): array
    {
        $overallMarks = $this->aggregateMarks($assessments, $linkedMarks);

        return $this->summateAggregatedMarks($overallMarks);
    }


    /**
     * Resolves the aggregated counts to summate final capability/indicator marks
     *
     * @param array $overallMarks
     * @return array
     * @throws InvalidArgumentException
     * @throws RangeException
     */
    private function summateAggregatedMarks(array $overallMarks): array
    {

        $markScheme = new MarkScheme\Percentage();

        foreach ($overallMarks as $capabilityId => $capabilityMarks) {
            foreach ($capabilityMarks['marks'] as $studentId => $sums) {
                $sumNormalised = $sums['sumNormalised'];
                $sumWeight     = $sums['sumWeight'];
                if (!$sumWeight) {
                    unset($overallMarks[$capabilityId]['marks'][$studentId]);
                } else {
                    $normalised                                       = $sumNormalised / $sumWeight;
                    $overallMarks[$capabilityId]['marks'][$studentId] = [
                        'normalised'    => $normalised,
                        'humanReadable' => $markScheme->getHumanReadableMark($normalised),
                    ];
                }
            }
            foreach ($capabilityMarks['indicators'] as $indicatorId => $indicatorMarks) {
                foreach ($indicatorMarks['marks'] as $studentId => $sums) {
                    $sumNormalised = $sums['sumNormalised'];
                    $sumWeight     = $sums['sumWeight'];
                    if (!$sumWeight) {
                        unset($overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]);
                    } else {
                        $normalised                                                                   = $sumNormalised / $sumWeight;
                        $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId] = [
                            'normalised'    => $normalised,
                            'humanReadable' => $markScheme->getHumanReadableMark($normalised),
                        ];
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
                                'sumNormalised' => null,
                                'sumWeight'     => null,
                            ];
                        }
                        // Does student have a mark for this capability for this assessment?
                        $capabilityMark = $studentData['assessments'][$assessmentId]['capabilities'][$capabilityId]['normalisedMark'] ?? null;
                        if ($capabilityMark !== null) {
                            $overallMarks[$capabilityId]['marks'][$studentId]['sumNormalised'] += $capabilityMark * $assessmentWeight;
                            $overallMarks[$capabilityId]['marks'][$studentId]['sumWeight']     += $assessmentWeight;
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
                                'sumNormalised' => null,
                                'sumWeight'     => null,
                            ];
                        }
                        // Does student have a mark for this indicator for this assessment?
                        $indicatorMark = $studentData['assessments'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['normalisedMark'] ?? null;
                        if ($indicatorMark !== null) {
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]['sumNormalised'] += $indicatorMark * $assessmentWeight;
                            $overallMarks[$capabilityId]['indicators'][$indicatorId]['marks'][$studentId]['sumWeight']     += $assessmentWeight;
                        }
                    }
                }
            }
        }
        return $overallMarks;
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
        $derivedMarks  = $this->generateHumanReadableMarks($databaseMarks, $assessments);
        $studentMarks  = $this->insertFolderMembers($derivedMarks, $folder);
        $exclusions    = $this->getExclusions($assessmentIds);
        $linkedMarks   = $this->insertSummaryPageLinks($studentMarks, $assessmentIds, $exclusions);
        $overallMarks  = $this->buildOverallMarks($assessments, $linkedMarks);
        return [$assessments, $exclusions, $linkedMarks, $overallMarks];
    }
}
