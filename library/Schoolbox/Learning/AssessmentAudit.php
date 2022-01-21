<?php

namespace Schoolbox\Learning;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Collection;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder;

class AssessmentAudit
{
    /**
     * @var FolderMapper
     */
    private $folderMapper;
    /**
     * for building the assessment query
     * @var IConnector $database
     */
    private IConnector $database;

    /**
     * @param FolderMapper$folderMapper
     * @param IConnector $database
     */
    public function __construct(FolderMapper $folderMapper, IConnector $database)
    {
        $this->folderMapper = $folderMapper;
        $this->database     = $database;
    }

    /**
     * downloads csv of all assessments within a folder
     *
     *
     * @api   GET /learning/assessment-audit/{folder}
     *
     * @param Folder\Model $folder
     */
    public function getAssessmentAudit(Folder\Model $folder)
    {
        /**
         * get all folders that we will be needing
         * build and combine the folder array and hydrate the objects
         */
//        //get all children folders
        $childrenFolders = $this->folderMapper->findAllChildren($folder->getId(), true);
        $allFolders = new Collection([$folder]);
        $allFolders = $allFolders->merge($childrenFolders);
        $allFolders = $this->folderMapper->hydrateCollection($allFolders);

        /**
         * grab all assignments for folderIds
         * with all the required information:
         */

        //grab all assignments and associated data
        $statement = $this->database->prepareQuery("SELECT
        v_assessment_relation.assessment_id AS assessmentId,
        assessment.title AS assessmentTitle,
        v_assessment_relation.folder_id AS folderID,
        lu_work_type.name AS assessmentWorkType,
        v_assessment_relation.course_implementation_id AS course_implementation_id,
        v_assessment_relation.course_unit_implementation_id AS course_unit_implementation_id,
        v_assessment_relation.course_specification_id AS course_specification_id,
        v_assessment_relation.course_unit_specification_id AS course_unit_specification_id,
        submission_box.accept_submission AS acceptSubmission,
        submission_box.multi_submission AS multiSubmission,
        submission_box.use_rubric AS useRubric,
        submission_box.similarity_check_enabled AS simCheck,
        markTypeTable.hr_name AS markType,
        gradeGroupTable.name AS gradeGroup,
        assessment.course_managed AS commonAssessment,
        capabilityMarkTypeTable.hr_name AS capabilityMarkType,
        capabilityGradeGroupTable.name AS capabilityGradeGroup

        FROM v_assessment_relation
        JOIN assessment ON assessment_id = assessment.id
        JOIN submission_box ON assessment.id = submission_box.assessment_id
        LEFT JOIN lu_work_type ON assessment.work_type_id = lu_work_type.id
        LEFT JOIN submission_mark_type as markTypeTable ON submission_box.mark_type = markTypeTable.mark_type_id
        LEFT JOIN submission_mark_type as capabilityMarkTypeTable ON submission_box.rubric_capability_mark_type_id = capabilityMarkTypeTable.mark_type_id
        LEFT JOIN submission_grade_group as gradeGroupTable ON submission_box.submission_grade_group_id = gradeGroupTable.id
        LEFT JOIN submission_grade_group as capabilityGradeGroupTable ON submission_box.rubric_capability_grade_group_id = capabilityGradeGroupTable.id
        WHERE v_assessment_relation.folder_id IN (" . implode(',', $allFolders->getPropertyValues('id')) . ")");


        //build the csv
        $rowHeadings = ['AssessmentId', 'AssessmentTitle', 'FolderId', 'FolderName', 'PageYearLevel', 'ClassCode', 'CourseFolderId',
                        'UnitFolderId', 'WorkType', 'CommonAssessment', 'AcceptOnlineSubmissions', 'SimilarityCheck', 'MarkType',
                        'MarkGradingSchemeScale', 'Rubric', 'CapabilityMarkType', 'CapabilityGradingSchemeScale'];
        header('Content-type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"assessment-audit.csv\"");

        $fp = fopen("php://output", 'w');
        fputcsv($fp, $rowHeadings);

        $statement->execute();
        /**
         * send csv data to the client
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            //grab COURSE specificationId or implementationId
            $courseFolderId = null;
            if (null !== $row['course_specification_id']) {
                $courseFolderId = $row['course_specification_id'];
            } elseif (null !== $row['course_implementation_id']) {
                $courseFolderId = $row['course_implementation_id'];
            }

            //grab UNIT specificationId or implementationId
            $unitCourseFolderId = null;
            if (null !== $row['course_unit_specification_id']) {
                $unitCourseFolderId = $row['course_unit_specification_id'];
            } elseif (null !== $row['course_unit_implementation_id']) {
                $unitCourseFolderId = $row['course_unit_implementation_id'];
            }

            //work out number of online submissions
            $onlineSubs = 'None';
            if ($row['multiSubmission']) {
                $onlineSubs = 'Multiple';
            } elseif ($row['acceptSubmission']) {
                $onlineSubs = 'Single';
            }

            //Grading and Capability Grading Scheme Scale, only show if using letter grade
            $markGradingSchemeScale = null;
            if ('Letter Grade' === $row['markType']) {
                $markGradingSchemeScale = $row['gradeGroup'];
            }
            $capabilityGradeSchemeScale = null;
            if ('Letter Grade' === $row['capabilityMarkType']) {
                $capabilityGradeSchemeScale = $row['capabilityGradeGroup'];
            }

            //build the row and send
            fputcsv($fp, [
                $row['assessmentId'],
                $row['assessmentTitle'],
                $row['folderID'],
                $allFolders[$row['folderID']]->getName(),
                implode(',', $allFolders[$row['folderID']]->getYearLevels()->getPropertyValues('name')),
                $allFolders[$row['folderID']]->getCodeString(),
                $courseFolderId,
                $unitCourseFolderId,
                $row['assessmentWorkType'],
                $this->boolToString($row['commonAssessment']),
                $onlineSubs,
                $this->boolToString($row['simCheck']),
                $row['markType'],
                $markGradingSchemeScale,
                $this->boolToString($row['useRubric']),
                $row['capabilityMarkType'],
                $capabilityGradeSchemeScale
            ]);
        }

        //all done, close the file and exit
        fclose($fp);
    }

    /**
     * Helper function to make it easier to read string bool than 1 / 0 in the csv export
     * @param bool $value
     * @return string
     */
    private function boolToString(bool $value): string
    {
        switch ($value) {
            case 1:
                return 'true';
            default:
                return 'false';
        }
    }
}
