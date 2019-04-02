<?php
/**
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric\Markbook;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Alaress\Database\Connector\IConnector;
use Schoolbox\Learning\MarkScheme;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Rubric\Grid\CapabilityUsage;
use Schoolbox\Assessment\Rubric\RowSet\Capability;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group as GradeGroup;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\UserMapper;
use External_ClassInterface;

class Controller extends Base
{

    /**
     * @var IConnector
     */
    private $internalDb;

    /**
     * Data source for class lists
     *
     * @var External_ClassInterface
     */
    protected $externalDb;

    /**
     * @var GradeGroup\Mapper
     */
    private $gradeGroupMapper;

    /**
     * Data source for group lists
     *
     * @var UserAccessMapper
     */
    protected $userAccessMapper;

    /**
     * Data source for users themselves
     *
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Constructor
     *
     * @param External_ClassInterface $externalDb
     * @param IConnector $internalDb
     * @param GradeGroup\Mapper $gradeGroupMapper
     * @param UserAccessMapper        $userAccessMapper
     * @param UserMapper              $userMapper
     */
    public function __construct(
        IConnector $internalDb,
        GradeGroup\Mapper $gradeGroupMapper,
        External_ClassInterface $externalDb,
        UserAccessMapper $userAccessMapper,
        UserMapper $userMapper
    ) {
        $this->externalDb         = $externalDb;
        $this->internalDb         = $internalDb;
        $this->gradeGroupMapper   = $gradeGroupMapper;
        $this->userAccessMapper   = $userAccessMapper;
        $this->userMapper         = $userMapper;
    }

    /**
     * Displays a list of rubric marks for students in the specified class
     *
     * @api   GET /learning/rubricMarkbook/{folderId}
     * @level 190
     * @param Folder\Model $folder                    Folder for this class
     * @throws ServiceNotFound                       If unable to locate HTML web Output
     */
    public function getMarkbook(Folder\Model $folder): void
    {
        $assessments = $this->retrieveAssessmentsInFolder($folder->getId());
        $students = $this->retrieveStudentsAndMarksInFolder($folder, $assessments);

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@rubricMarkbook/markbookOuter.twig',
                [
                    'students' => $students,
                    'assessments' => $assessments,
                    'folder' => $folder
                ]
            )->setTab('submission')->setTitle('Rubric Markbook for ' . $folder->getName())
        );
    }

    public function retrieveAllUsersInFolder(Folder\Model $folder) {
        $usersInClass = $this->externalDb->getStudents($folder->getCodeString());
        $hydratedUsersInClass = $this->userMapper->findByColumn('synergy_id', array_column($usersInClass, 'synergy_id'));
        $usersInGroup = $this->userAccessMapper->findUsersInCategory($folder, null, UserAccess::ACCESS_READ, false, true);

        // Combine class and group members
        return $hydratedUsersInClass->mergeUnique($usersInGroup, 'id');
    }

    /**
     * Find all the assessments for a given folder, and return them indexed by assessment ID
     *
     * @param int $folderId
     * @return array
     * @throws /Aura/Router/Exception/RouteNotFound
     */
    private function retrieveAssessmentsInFolder(int $folderId) : array
    {
        $getAllAssessmentsInFolderQuery = $this->internalDb->prepareQuery(
            "SELECT a.id as assessment_id,
            a.title AS assessment_name,
            sb.rubric_group_marks AS assessment_rubric_group_marks,
            sb.submission_grade_group_id AS assessment_grade_group_id,
            sb.mark_type as assessment_mark_type,
            sb.max_mark as assessment_max_mark,
            rgu.rubric_group_id AS capability_id,
            vrm.capability_max_value as capability_max_value,
            rg.name AS capability_name,
            rcu.criterion_id AS indicator_id,
            vrm.indicator_max_value as indicator_max_value,
            rc.name AS indicator_name
            FROM  assessment a
            INNER JOIN submission_box sb ON sb.assessment_id = a.id
            INNER JOIN rubric_group_usage rgu ON sb.id = rgu.submission_box_id
            INNER JOIN rubric_group rg ON rgu.rubric_group_id = rg.id
            INNER JOIN rubric_criterion_usage rcu ON sb.id = rcu.submission_box_id
            INNER JOIN rubric_criterion rc ON rcu.criterion_id = rc.id
                  AND rc.rubric_group_id = rg.id
            LEFT  JOIN v_rubric_mark vrm ON vrm.assessment_id = a.id
                  AND vrm.indicator_id = rc.id
                  AND vrm.capability_id = rg.id
            INNER JOIN v_most_recent_feedback vmrf ON vmrf.assessment_id = vrm.assessment_id
                  AND vmrf.student_id = vrm.student_id
                  AND vmrf.feedback_id = vrm.feedback_id
                  AND vmrf.feedback_table = 'submission_return'
            WHERE sb.category=:folderId AND a.deleted_at IS NULL
            ORDER BY a.id, rgu.rubric_group_id, rcu.criterion_id;"
        );

        $getAllAssessmentsInFolderQuery->execute(['folderId' => $folderId]);

        $assessments = [];
        $capabilities = [];
        $indicators = [];

        $lastObservedAssessmentId = null;
        $lastObservedCapabilityId = null;

        foreach ($getAllAssessmentsInFolderQuery->getIterator() as $i => $row) {
            if ($lastObservedAssessmentId !== $row['assessment_id']) {
                // Has the assessment we're looking at changed? Reset the capabilities
                $capabilities = [];
            }

            if ($lastObservedCapabilityId !== $row['capability_id']) {
                // Has the capability we're looking at changed? Reset the indicators
                $indicators = [];
            }

            $indicators[$row['indicator_id']] = [
                'name' => $row['indicator_name'],
                'maxMark' => $row['indicator_max_value'],
            ];

            $capabilities[$row['capability_id']] = [
                'name' => $row['capability_name'],
                'maxMark' => $row['capability_max_value'],
                'indicators' => $indicators
            ];

            // Stuff everything about the assessment into an array
            $assessments[$row['assessment_id']] = [
                'name' => $row['assessment_name'],
                'statusLink' => $this->getRouter()->generate('learningAssessment.getStatus', [ 'assessmentId' => $row['assessment_id'] ]),
                'maxMark' => $row['assessment_max_mark'],
                'markType' => $row['assessment_mark_type'],
                'gradeGroupId' => $row['assessment_grade_group_id'],
                'usesMarkPerCap' => $row['assessment_rubric_group_marks'],
                'capabilities' => $capabilities
            ];

            $lastObservedAssessmentId = $row['assessment_id'];
            $lastObservedCapabilityId = $row['capability_id'];
        }

        uasort(
            $assessments,
            function (array $a, array $b) : int {
                return strnatcmp($a['name'], $b['name']);
            }
        );
        
        return $assessments;
    }

    /**
     * Find each student in a folder and their marks (if extant), and return an array indexed by student ID
     *
     * @param Folder\Model $folder
     * @param array $assessmentsInFolder
     * @return array
     */
    private function retrieveStudentsAndMarksInFolder(Folder\Model $folder, array $assessmentsInFolder) : array
    {
        $getAuthoritativeMarksInFolderQuery = $this->internalDb->prepareQuery(
            "SELECT var.assessment_id,
            vrm.student_id,
            u.firstname AS student_firstname,
            u.lastname AS student_lastname,
            vrm.capability_id,
            vrm.capability_mark,
            vrm.indicator_id,
            vrm.indicator_mark,
            vrm.descriptor_name
            FROM       v_rubric_mark vrm
            INNER JOIN v_assessment_relation var ON vrm.assessment_id = var.assessment_id
            INNER JOIN v_most_recent_feedback vmrf ON var.assessment_id = vmrf.assessment_id AND vrm.student_id = vmrf.student_id
            INNER JOIN user u ON u.id = vrm.student_id
            INNER JOIN assessment a ON a.id = var.assessment_id
            WHERE      var.folder_id=:folderId AND vrm.feedback_type=:instructorReviewRoute AND vrm.feedback_deleted_at IS NULL AND a.deleted_at IS NULL
            ORDER BY vrm.student_id, var.assessment_id, vrm.capability_id, vrm.indicator_id"
        );

        $getAuthoritativeMarksInFolderQuery->execute(['folderId' => $folder->getId(), 'instructorReviewRoute' => ReturnedWork::REVIEW_ROUTE_INSTRUCTOR]);

        $students = [];
        $assessmentsForStudent = [];
        $capabilityMarks = [];
        $indicatorMarks = [];

        $lastObservedAssessmentId = null;
        $lastObservedCapabilityId = null;
        $lastObservedStudentId = null;

        foreach ($getAuthoritativeMarksInFolderQuery->getIterator() as $i => $row) {
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

            $indicatorMarks[$row['indicator_id']] = [
                'databaseMark' => $row['indicator_mark'],
                'selectedDescriptorText' => $row['descriptor_name']
            ];

            $capabilityMarks[$row['capability_id']] = [
                'databaseMark' => $row['capability_mark'],
                'indicators' => $indicatorMarks
            ];

            $assessmentsForStudent[$row['assessment_id']] = [
                'capabilities' => $capabilityMarks
            ];

            // Stuff everything about the students marks into an array
            $students[$row['student_id']] = [
                'firstname' => $row['student_firstname'],
                'lastname' => $row['student_lastname'],
                'assessments' => $assessmentsForStudent,
                'profileLink' => '/search/user/' . $row['student_id']
            ];

            $lastObservedAssessmentId = $row['assessment_id'];
            $lastObservedCapabilityId = $row['capability_id'];
            $lastObservedStudentId = $row['student_id'];
        }

        // Combine students who have marks with those who are in the class or group, but have no marks yet
        $allUsersInFolder = $this->retrieveAllUsersInFolder($folder);
        foreach($allUsersInFolder as $user) {
            $userId = $user->getId();
            if (! isset($students[$userId])) {
                $students[$userId] = [
                    'firstname' => $user->getFirstname(),
                    'lastname' => $user->getLastname(),
                    'profileLink' => '/search/user/' . $userId,
                    'assessments' => []
                ];
            }
        }

        return $this->generateHumanReadableMarks($students, $assessmentsInFolder);
    }

    /**
     * Work out the human readable marks from the raw marks and insert them into the student marks array
     *
     * @return array
     * @param array $students
     * @param array $assessmentsInFolder
     * @throws /Aura/Router/Exception/RouteNotFound
     */
    private function generateHumanReadableMarks(array $students, array $assessmentsInFolder) : array
    {
        $markSchemeFactory = new MarkScheme\Factory();

        foreach($students as $studentId => $student) {

            foreach($student['assessments'] as $assessmentId => $assessmentMarks) {
                $summaryLink =  $this->getRouter()->generate(
                    'learningAssessment.getSummary',
                    ['studentId' => $studentId, 'assessmentId' => $assessmentId]
                );
                $students[$studentId]['assessments'][$assessmentId]['summaryLink'] = $summaryLink;

                $assessmentMaxMark = $assessmentsInFolder[$assessmentId]['maxMark'];
                $assessmentMarkType = $assessmentsInFolder[$assessmentId]['markType'];
                $assessmentUsesMarkPerCap = $assessmentsInFolder[$assessmentId]['usesMarkPerCap'];

                // Create some things the markscheme factory wants our Due Work to have
                try {
                    $gradeGroup = $this->gradeGroupMapper->findById($assessmentsInFolder[$assessmentId]['gradeGroupId']);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $gradeGroup = null;
                }

                $markType = new MarkType();
                $markType->setId($assessmentMarkType);

                // Fill out a Due Work with the data we need for the markscheme factory to work
                $stubAssessment = new DueWork();
                $stubAssessment->setId($assessmentId);
                $stubAssessment->setMarkPerCapability($assessmentUsesMarkPerCap);
                $stubAssessment->setMaxMark($assessmentMaxMark);
                $stubAssessment->setMarkType($markType);
                if ($gradeGroup !== null) {
                    $stubAssessment->setGradeGroup($gradeGroup);
                }

                foreach($assessmentMarks['capabilities'] as $capabilityId => $capabilityMarks) {
                    $capabilityDatabaseMark = $capabilityMarks['databaseMark'];
                    $capabilityInFolder = $assessmentsInFolder[$assessmentId]['capabilities'][$capabilityId];

                    if ($capabilityDatabaseMark !== null) {
                        $capabilityMaxMark = $capabilityInFolder['maxMark'];

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

                    foreach($capabilityMarks['indicators'] as $indicatorId => $indicatorMarks) {
                        $indicatorInFolder = $capabilityInFolder['indicators'][$indicatorId];
                        $indicatorMaxMark = $indicatorInFolder['maxMark'];
                        $indicatorDatabaseMark = $indicatorMarks['databaseMark'];

                        // This is always just a "total mark" type, so we can work this out ourselves
                        // No need for creating all the models in order to calculate it -
                        // and indeed the overhead in doing so is prohibitive
                        $humanReadableIndicatorMark = $indicatorDatabaseMark !== null
                            ? round($indicatorDatabaseMark, 2) . ' / ' . round($indicatorMaxMark)
                            : null;
                        $normalisedIndicatorMark = $indicatorMaxMark !== 0
                            && $indicatorMaxMark !== null
                            && $indicatorDatabaseMark !== null
                            ? ($indicatorDatabaseMark / $indicatorMaxMark) * 100
                            : null;
                        $students[$studentId]['assessments'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['humanReadableMark'] = $humanReadableIndicatorMark;
                        $students[$studentId]['assessments'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['normalisedMark'] = $normalisedIndicatorMark;
                    }
                }
            }

        }

        uasort(
            $students,
            function (array $a, array $b) : int {
                return strnatcmp($a['lastname'], $b['lastname']);
            }
        );

        return $students;
    }

}
