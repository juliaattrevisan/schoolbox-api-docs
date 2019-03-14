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
use Schoolbox\Assessment\Exception;
use Schoolbox\Learning\MarkScheme;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group as GradeGroup;
use PDO;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Alaress_Probe as Probe;

class Controller extends Base
{

    /**
     * @var IConnector
     */
    private $database;


    /**
     * @var GradeGroup\Mapper
     */
    private $gradeGroupMapper;

    /**
     * Constructor
     *
     * @param IConnector $database
     * @param GradeGroup\Mapper $gradeGroupMapper
     */
    public function __construct(IConnector $database, GradeGroup\Mapper $gradeGroupMapper)
    {
        $this->database = $database;
        $this->gradeGroupMapper = $gradeGroupMapper;
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
        $studentMarks = $this->retrieveMarksInFolder($folder->getId());

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@rubricMarkbook/markbookOuter.twig',
                [
                    'studentMarks' => $studentMarks,
                    'assessments' => $assessments,
                    'folder' => $folder
                ]
            )->setTab('submission')->setTitle('Rubric Markbook for ' . $folder->getName())
        );
    }

    private function retrieveAssessmentsInFolder(int $folderId) : array
    {
        $getAllAssessmentsInFolderQuery = $this->database->prepareQuery(
            "SELECT var.assessment_id,
            a.title AS assessment_name,
            sb.rubric_group_marks AS assessment_rubric_group_marks,
            vrm.assessment_mark_type,
            vrm.assessment_max_mark,
            vrm.capability_id,
            vrm.capability_name,
            vrm.capability_max_value,
            vrm.indicator_id,
            vrm.indicator_name,
            vrm.indicator_max_value
            FROM       v_rubric_mark vrm
            INNER JOIN v_assessment_relation var ON vrm.assessment_id = var.assessment_id
            INNER JOIN assessment a ON a.id = vrm.assessment_id
            INNER JOIN submission_box sb ON sb.assessment_id = vrm.assessment_id
            WHERE      var.folder_id=:folderId"
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
                'maxMark' => $row['assessment_max_mark'],
                'markType' => $row['assessment_mark_type'],
                'usesMarkPerCap' => $row['assessment_rubric_group_marks'],
                'capabilities' => $capabilities
            ];

            $lastObservedAssessmentId = $row['assessment_id'];
            $lastObservedCapabilityId = $row['capability_id'];
        }

        new Probe($assessments);

        return $assessments;
    }

    private function retrieveMarksInFolder(int $folderId) : array
    {
        $getAuthoritativeMarksInFolderQuery = $this->database->prepareQuery(
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
            INNER JOIN v_most_recent_feedback vmrf ON var.assessment_id = vmrf.assessment_id
            INNER JOIN user u ON u.id = vrm.student_id
            WHERE      var.folder_id=:folderId and vrm.feedback_type='instructor'
            ORDER BY vrm.student_id, var.assessment_id"
        );

        $getAuthoritativeMarksInFolderQuery->execute(['folderId' => $folderId]);

        $students = [];
        $assessmentsForStudent = [];
        $capabilityMarks = [];
        $indicatorMarks = [];

        $lastObservedAssessmentId = null;
        $lastObservedCapabilityId = null;
        $lastObservedStudentId = null;

        foreach ($getAuthoritativeMarksInFolderQuery->getIterator() as $i => $row) {
            if ($lastObservedAssessmentId !== $row['assessment_id']) {
                // Has the assessment we're looking at changed? Reset the capabilities
                $capabilityMarks = [];
            }

            if ($lastObservedCapabilityId !== $row['capability_id']) {
                // Has the capability we're looking at changed? Reset the indicators
                $indicatorMarks = [];
            }

            if ($lastObservedStudentId !== $row['student_id']) {
                // Has the student we're looking at changed? Reset the list of assessments for them
                $assessmentsForStudent = [];
            }

            $indicatorMarks[$row['indicator_id']] = [
                'normalisedMark' => $row['indicator_mark'],
                'selectedDescriptorText' => $row['descriptor_name']
            ];

            $capabilityMarks[$row['capability_id']] = [
                'normalisedMark' => $row['capability_mark'],
                'indicators' => $indicatorMarks
            ];

            $assessmentsForStudent[$row['assessment_id']] = [
                'capabilities' => $capabilityMarks
            ];

            // Stuff everything about the students marks into an array
            $students[$row['student_id']] = [
                'firstname' => $row['student_firstname'],
                'lastname' => $row['student_lastname'],
                'assessments' => $assessmentsForStudent
            ];

            $lastObservedAssessmentId = $row['assessment_id'];
            $lastObservedCapabilityId = $row['capability_id'];
            $lastObservedStudentId = $row['student_id'];
        }

        return $this->generateHumanReadableMarks($students, $folderId);
    }

    /**
     * @return array
     * @param array $students
     * @param int $folderId
     */
    private function generateHumanReadableMarks(array $students, int $folderId) : array
    {
        $assessmentsInFolder = $this->retrieveAssessmentsInFolder($folderId);

        foreach($students as $studentId => $student) {

            foreach($student['assessments'] as $assessmentId => $assessmentMarks) {
                $assessmentMaxMark = $assessmentsInFolder[$assessmentId]['maxMark'];
                $assessmentMarkType = $assessmentsInFolder[$assessmentId]['markType'];
                $assessmentUsesMarkPerCap = $assessmentsInFolder[$assessmentId]['usesMarkPerCap'];

                $markSchemeFactory = new MarkScheme\Factory();
                // Create some things the markscheme factory wants our Due Work to have
                $gradeGroupIdQuery = $this->database->prepareQuery(
                    "SELECT sb.submission_grade_group_id
                    FROM submission_box sb
                    WHERE sb.assessment_id = :assessmentId"
                );
                $gradeGroupIdQuery->execute(['assessmentId' => $assessmentId]);
                $gradeGroupId = $gradeGroupIdQuery->fetchAll(PDO::FETCH_COLUMN);
                try {
                    $gradeGroup = $this->gradeGroupMapper->findById($gradeGroupId);
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
                    /**
                     * @var GradeGroup\Model $gradeGroup
                     */
                    $stubAssessment->setGradeGroup($gradeGroup);
                }

                foreach($assessmentMarks['capabilities'] as $capabilityId => $capabilityMarks) {
                    $capabilityInFolder = $assessmentsInFolder[$assessmentId]['capabilities'][$capabilityId];
                    $capabilityMaxMark = $capabilityInFolder['maxMark'];
                    $capabilityNormalisedMark = $capabilityMarks['normalisedMark'];

                    $capabilityMarkScheme = $markSchemeFactory->spawnForCapability($stubAssessment, $capabilityMaxMark);

                    $humanReadableCapabilityMark = $capabilityMarkScheme->getHumanReadableMark($capabilityNormalisedMark);
                    $students[$studentId]['assessments'][$assessmentId]['capabilities']['humanReadableMark'] = $humanReadableCapabilityMark;

                    foreach($capabilityMarks['indicators'] as $indicatorId => $indicatorMarks) {
                        $indicatorInFolder = $capabilityInFolder['indicators'][$indicatorId];
                        $indicatorMaxMark = $indicatorInFolder['maxMark'];
                        $indicatorNormalisedMark = $indicatorMarks['normalisedMark'];

                        // TODO this is probably wrong??? look at examples
                        $assessmentMarkScheme = $markSchemeFactory->spawnForCapability($stubAssessment, $indicatorMaxMark);
                        $humanReadableIndicatorMark = $assessmentMarkScheme->getHumanReadableMark($indicatorNormalisedMark);
                        $students[$studentId]['assessments'][$assessmentId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['humanReadableMark'] = $humanReadableIndicatorMark;
                    }
                }
            }

        }

        new Probe($students);

        return $students;
    }
}
