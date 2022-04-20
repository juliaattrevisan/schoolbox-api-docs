<?php

/**
 * Downloads csv of all assessments within a folder and children folders
 * where the assessments are not deleted
 * /learning/assessment-audit/{folder}
 *
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

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
    private FolderMapper $folderMapper;
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

        course_implementation.folder_id as course_implementation_folder_id,
        course_unit_implementation_out.folder_id as course_unit_implementation_folder_id,
        COALESCE(course_specification.folder_id, course_imp_spec.folder_id) AS course_specification_folder_id,
        course_unit_specification.folder_id as course_unit_specification_folder_id,

        submission_box.accept_submission AS acceptSubmission,
        submission_box.multi_submission AS multiSubmission,
        submission_box.use_rubric AS useRubric,
        submission_box.similarity_check_enabled AS simCheck,
        markTypeTable.hr_name AS markType,
        gradeGroupTable.name AS gradeGroup,
        assessment.course_managed AS commonAssessment,
        capabilityMarkTypeTable.hr_name AS capabilityMarkType,
        capabilityGradeGroupTable.name AS capabilityGradeGroup,
        assessment.weight as weight,
        submission_box.self_reviewability as selfReviewability,
        submission_box.mark_visibility as markVisibility,
        assessment.publish_date as publishDate,
        assessment.due_date as dueDate,
        unit_spec_folder.name as unitSpecName,
        unit_imp_folder.name as unitImpName,
        COALESCE(course_spec_folder.name, course_imp_spec_folder.name) as courseName

        FROM v_assessment_relation
        JOIN assessment ON assessment_id = assessment.id
        JOIN submission_box ON assessment.id = submission_box.assessment_id
        LEFT JOIN lu_work_type ON assessment.work_type_id = lu_work_type.id
        LEFT JOIN submission_mark_type as markTypeTable ON submission_box.mark_type = markTypeTable.mark_type_id
        LEFT JOIN submission_mark_type as capabilityMarkTypeTable ON submission_box.rubric_capability_mark_type_id = capabilityMarkTypeTable.mark_type_id
        LEFT JOIN submission_grade_group as gradeGroupTable ON submission_box.submission_grade_group_id = gradeGroupTable.id
        LEFT JOIN submission_grade_group as capabilityGradeGroupTable ON submission_box.rubric_capability_grade_group_id = capabilityGradeGroupTable.id

        LEFT JOIN assessment_course_unit_implementation on v_assessment_relation.assessment_id = assessment_course_unit_implementation.assessment_id
        LEFT JOIN course_specification on v_assessment_relation.course_specification_id = course_specification.id
        LEFT JOIN course_implementation on v_assessment_relation.course_implementation_id = course_implementation.id
        LEFT JOIN course_specification course_imp_spec on course_implementation.course_specification_id = course_imp_spec.id
        LEFT JOIN course_unit_specification on v_assessment_relation.course_unit_specification_id = course_unit_specification.id
        LEFT JOIN course_unit_implementation on v_assessment_relation.course_unit_implementation_id = course_unit_implementation.id
        LEFT JOIN course_unit_specification as course_unit_implementation_out on course_unit_implementation.course_unit_specification_id = course_unit_implementation_out.id
        LEFT JOIN folder as unit_spec_folder on course_unit_specification.folder_id = unit_spec_folder.id
        LEFT JOIN folder as unit_imp_folder on course_unit_implementation_out.folder_id = unit_imp_folder.id
        LEFT JOIN folder as course_imp_spec_folder on course_imp_spec.folder_id = course_imp_spec_folder.id
        LEFT JOIN folder as course_spec_folder on course_specification.folder_id = course_spec_folder.id

        WHERE v_assessment_relation.folder_id IN (" . implode(',', $allFolders->getPropertyValues('id')) . ")
        AND assessment.deleted_at IS NULL");


        //build the csv
        $rowHeadings = ['AssessmentId', 'AssessmentTitle', 'FolderId', 'FolderName', 'PageYearLevel', 'ClassCode', 'CourseFolderId', 'CourseName',
                        'UnitFolderId', 'UnitName', 'WorkType', 'CommonAssessment', 'AcceptOnlineSubmissions', 'SimilarityCheck', 'MarkType',
                        'MarkGradingSchemeScale', 'Rubric', 'CapabilityMarkType', 'CapabilityGradingSchemeScale', 'Weight', 'SpecificationImplementation', 'PublishDate', 'DueDate', 'SelfReviewability', 'MarkVisibility'];
        header('Content-type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"assessment-audit.csv\"");

        $fp = fopen("php://output", 'w');
        fputcsv($fp, $rowHeadings);

        $statement->execute();
        /**
         * send csv data to the client
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            //set if the assignment is a Specification or Implementation or not
            $specImplem = 'Class Created';
            //grab COURSE specificationId or implementationId
            $courseFolderId = null;
            if (null !== $row['course_specification_folder_id']) {
                $specImplem = 'Course';
                $courseFolderId = $row['course_specification_folder_id'];
            } elseif (null !== $row['course_implementation_folder_id']) {
                $specImplem = 'Class Imported';
                $courseFolderId = $row['course_implementation_folder_id'];
            }

            //grab UNIT specificationId or implementationId
            $unitCourseFolderId = null;
            $unitFolderName = null;
            if (null !== $row['course_unit_specification_folder_id']) {
                $specImplem = 'Course';
                $unitCourseFolderId = $row['course_unit_specification_folder_id'];
                $unitFolderName = $row['unitSpecName'];
            } elseif (null !== $row['course_unit_implementation_folder_id']) {
                $specImplem = 'Class Imported';
                $unitCourseFolderId = $row['course_unit_implementation_folder_id'];
                $unitFolderName = $row['unitImpName'];
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

            //Mark Visibility strings
            $markVisibility = [
                0 => 'Staff Only',
                1 => 'Staff & Student',
                2 => 'Staff, Student & Parent',
            ];

            //build the row and send
            fputcsv($fp, [
                $row['assessmentId'],
                $row['assessmentTitle'],
                $row['folderID'],
                $allFolders[$row['folderID']]->getName(),
                implode(',', $allFolders[$row['folderID']]->getYearLevels()->getPropertyValues('name')),
                $allFolders[$row['folderID']]->getCodeString(),
                $courseFolderId,
                $row['courseName'],
                $unitCourseFolderId,
                $unitFolderName,
                $row['assessmentWorkType'],
                $this->boolToString($row['commonAssessment']),
                $onlineSubs,
                $this->boolToString($row['simCheck']),
                $row['markType'],
                $markGradingSchemeScale,
                $this->boolToString($row['useRubric']),
                $row['capabilityMarkType'],
                $capabilityGradeSchemeScale,
                $row['weight'],
                $specImplem,
                $row['publishDate'],
                $row['dueDate'],
                $row['selfReviewability'],
                $markVisibility[$row['markVisibility']],
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
        return $value ? 'true' : 'false';
    }
}
