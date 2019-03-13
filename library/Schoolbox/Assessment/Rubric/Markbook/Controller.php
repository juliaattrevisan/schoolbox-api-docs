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

        foreach ($getAllAssessmentsInFolderQuery->getIterator() as $i => $row) {
            // Stuff everything about the assessment into an array
            $assessments[$row['assessment_id']] = [
                'name' => $row['assessment_name'],
                'maxMark' => $row['assessment_max_mark'],
                'capabilities' => [
                    $row['capability_id'] => [
                        'name' => $row['capability_name'],
                        'maxMark' => $row['capability_max_value'],
                        'indicators' => [
                            $row['indicator_id'] => [
                                'name' => $row['indicator_name'],
                                'maxMark' => $row['indicator_max_value']
                            ]
                        ]
                    ]
                ]
            ];

            $marktype = null;
            switch ($row['assessment_mark_type']) {
                // Yes, I know these are deprecated, but we have to use them
                case MarkType::TYPE_PERCENT:
                    $marktype = new MarkScheme\Percentage();
                    break;
                case MarkType::TYPE_NONE:
                    $marktype = new MarkScheme\None();
                    break;
                case MarkType::TYPE_TOTAL:
                    $maxMark = $row['capability_max_value'] ?? $row['assessment_max_mark'];
                    $marktype = new MarkScheme\TotalMarkFractional($maxMark);
                    break;
                case MarkType::TYPE_LETTER:
                    $getGradeGroupIdQuery = $this->database->prepareQuery(
                        "SELECT sb.submission_grade_group_id
                    FROM submission_box sb
                    WHERE sb.assessment_id = :assessmentId
                    LIMIT 1");
                    $getGradeGroupIdQuery->execute(['assessmentId' => (int)$row['assessment_id']]);
                    $gradeGroupId = $getGradeGroupIdQuery->fetchAll(PDO::FETCH_COLUMN)[0];

                    /** @var GradeGroup\Model $gradeGroup */
                    $gradeGroup = $this->gradeGroupMapper->findById($gradeGroupId);

                    $marktype = new MarkScheme\LetterGrade($gradeGroup);
                    break;
                default:
                    throw new \DomainException('Invalid MarkType for DueWork: ' . $row['assessment_name']);
            }

            $markScheme = new MarkScheme\Percentage();
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
        }

        return [$assessments, $studentMarks];
    }

}
