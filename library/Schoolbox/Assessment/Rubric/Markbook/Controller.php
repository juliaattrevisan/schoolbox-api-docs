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
        [$assessments, $studentMarks] = $this->getMarkbookData($folder->getId());

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

    /**
     * @param int $folderId
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \Alaress\Database\Exception
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \RangeException
     */
    private function getMarkbookData(int $folderId) : array
    {
        $getAllAssessmentsInFolderQuery = $this->database->prepareQuery(
    "SELECT var.assessment_id,
            a.title AS assessment_name,
            vrm.assessment_mark_type,
            vrm.assessment_max_mark,
            vrm.student_id,
            u.firstname AS student_firstname,
            u.lastname AS student_lastname,
            vrm.capability_id,
            vrm.capability_name,
            vrm.capability_mark,
            vrm.capability_max_value,
            vrm.indicator_id,
            vrm.indicator_name,
            vrm.indicator_mark,
            vrm.indicator_max_value,
            vrm.descriptor_id,
            vrm.descriptor_name,
            vrm.descriptor_max_value
            FROM       v_rubric_mark vrm
            INNER JOIN v_assessment_relation var ON vrm.assessment_id = var.assessment_id
            INNER JOIN v_most_recent_feedback vmrf ON var.assessment_id = vmrf.assessment_id
            INNER JOIN assessment a ON a.id = vmrf.assessment_id
            INNER JOIN user u ON u.id = vrm.student_id
            WHERE      var.folder_id=:folderId and vrm.feedback_type='instructor'"
        );

        $getAllAssessmentsInFolderQuery->execute(['folderId' => $folderId]);

        $assessments = [];
        $studentMarks = [];

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
                'maxMark' => $row['indicator_max_value']
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
                'capabilities' => $capabilities
            ];

            $markSchemeFactory = new MarkScheme\Factory();

            // TODO hydrate assessment or something
            $capabilityMarkScheme = $markSchemeFactory->spawnForCapability($assessment, $row['capability_max_value']);
            $assessmentMarkScheme = $markSchemeFactory->spawnMarkSchemeFor($assessment);

            // TODO humanReadableMark is NOT right.
            $humanReadableCapabilityMark = $markScheme->getHumanReadableMark($row['capability_mark']);
            $humanReadableIndicatorMark = $markScheme->getHumanReadableMark($row['indicator_mark']);

            // Stuff everything about the students marks into an array
            $studentMarks[$row['student_id']] = [
                'firstname' => $row['student_firstname'],
                'lastname' => $row['student_lastname'],
                $row['assessment_id'] => [
                    'capabilities' => [
                        $row['capability_id'] => [
                            'mark' => $humanReadableCapabilityMark,
                            'indicators' => [
                                $row['indicator_id'] => [
                                    'mark' => $humanReadableIndicatorMark,
                                    'selectedDescriptorText' => $row['descriptor_name']
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $lastObservedAssessmentId = $row['assessment_id'];
            $lastObservedCapabilityId = $row['capability_id'];
        }

        return [$assessments, $studentMarks];
    }

}
