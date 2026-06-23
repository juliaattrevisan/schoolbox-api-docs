<?php

/**
 * Downloads a zip of assessment audit CSVs for all assessments within a folder and children folders
 * where the assessments are not deleted
 * /learning/assessment-audit/{folder}
 *
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning;

use Alaress\Database\Connector\IConnector;
use RuntimeException;
use Schoolbox\Collection;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use ZipArchive;

class AssessmentAudit
{
    private const ASSESSMENT_AUDIT_HEADINGS = [
        'AssessmentId',
        'ActivityType',
        'AssessmentTitle',
        'FolderId',
        'FolderName',
        'PageYearLevel',
        'ClassCode',
        'CourseFolderId',
        'CourseName',
        'SubjectCode',
        'UnitFolderId',
        'UnitName',
        'WorkType',
        'ActivityAudience',
        'MarkVisibility',
        'RequireAllActivities',
        'SummationMethod',
        'CommonAssessment',
        'AcceptOnlineSubmissions',
        'SimilarityCheck',
        'InputMarkType',
        'InputGradeGroup',
        'DisplayMarkType',
        'DisplayGradeGroup',
        'UseComments',
        'Rubric',
        'Weight',
        'SpecificationImplementation',
        'PublishDate',
        'DueDate',
        'SelfReviewability',
        'Authenticity',
        'SubmissionOptions',
        'ParentObjectDeleted',
    ];

    private const RUBRIC_AUDIT_HEADINGS = [
        'AssessmentId',
        'AssessmentTitle',
        'FolderId',
        'FolderName',
        'PageYearLevel',
        'ClassCode',
        'CourseFolderId',
        'CourseName',
        'SubjectCode',
        'UnitFolderId',
        'UnitName',
        'WorkType',
        'RubricID',
        'RubricTitle',
        'RubricWeight',
        'CapabilityMarkType',
        'CapabilityGradeGroup',
    ];

    /**
     * @var FolderMapper
     */
    private FolderMapper $folderMapper;
    /**
     * for building the assessment query
     * @var IConnector $database
     */
    private IConnector $database;

    private string $schoolCode;

    /**
     * @param FolderMapper$folderMapper
     * @param IConnector $database
     */
    public function __construct(FolderMapper $folderMapper, IConnector $database, string $schoolCode)
    {
        $this->folderMapper = $folderMapper;
        $this->database     = $database;
        $this->schoolCode   = $schoolCode;
    }

    /**
     * Downloads csvs of all assessments within a folder.
     *
     *
     * @api   GET /learning/assessment-audit/{folder}
     *
     * @param Folder\Model $folder
     */
    public function getAssessmentAudit(Folder\Model $folder): void
    {
        $childrenFolders = $this->folderMapper->findAllChildren($folder->getId(), true);
        $allFolders = new Collection([$folder]);
        $allFolders = $allFolders->merge($childrenFolders);

        $folderIds = implode(',', array_map('intval', $allFolders->getPropertyValues('id')));
        $timestamp = \Alaress_Date_Format::now()->toIso8601String();

        $assessmentCsv = $this->buildCsv(
            $this->getAssessmentAuditQuery($folderIds),
            self::ASSESSMENT_AUDIT_HEADINGS,
        );
        $rubricCsv = $this->buildCsv(
            $this->getRubricAuditQuery($folderIds),
            self::RUBRIC_AUDIT_HEADINGS,
        );

        $assessmentFilename = sprintf('assessment-audit-%s-%s.csv', $this->schoolCode, $timestamp);
        $rubricFilename = sprintf('rubric-audit-%s-%s.csv', $this->schoolCode, $timestamp);
        $zipFilename = sprintf('assessment-audit-%s-%s.zip', $this->schoolCode, $timestamp);

        $this->downloadZip($zipFilename, [
            $assessmentFilename => $assessmentCsv,
            $rubricFilename => $rubricCsv,
        ]);
    }

    private function buildCsv(string $query, array $headings): string
    {
        $statement = $this->database->prepareQuery($query);
        $statement->execute();

        $fp = fopen('php://temp', 'w+');
        fputcsv($fp, $headings, escape: '');

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            fputcsv(
                $fp,
                array_map(
                    static fn(string $heading) => $row[$heading] ?? null,
                    $headings,
                ),
                escape: '',
            );
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }

    private function downloadZip(string $filename, array $files): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'assessment-audit-');
        if (false === $zipPath) {
            throw new RuntimeException('Unable to create assessment audit zip file.');
        }

        $zipArchive = new ZipArchive();
        $zipStatus = $zipArchive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if (true !== $zipStatus) {
            throw new RuntimeException('Unable to open assessment audit zip file.');
        }

        foreach ($files as $fileName => $contents) {
            $zipArchive->addFromString($fileName, $contents);
        }
        $zipArchive->close();

        header('Content-Type: application/zip');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Content-Length: ' . filesize($zipPath));

        readfile($zipPath);
        unlink($zipPath);
    }

    private function getAssessmentAuditQuery(string $folderIds): string
    {
        return "
SELECT
    mv_assessment_relation.assessment_id AS AssessmentId,
    CASE
        WHEN project.id IS NOT NULL THEN 'Project'
        WHEN lti_assessment.id IS NOT NULL THEN '3rd Party Assessment'
        WHEN assess.assess_id IS NOT NULL THEN 'Quiz'
        WHEN reporting.id IS NOT NULL THEN 'Reporting Activity'
        WHEN submission_box.id IS NOT NULL THEN 'Due Work'
    END AS ActivityType,
    assessment.title AS AssessmentTitle,
    mv_assessment_relation.folder_id AS FolderId,
    folder.name AS FolderName,
    (
        SELECT GROUP_CONCAT(DISTINCT lu_year_level.name ORDER BY lu_year_level.sequence SEPARATOR ',')
        FROM folder_year_level
        JOIN lu_year_level ON folder_year_level.year_level_id = lu_year_level.id
        WHERE folder_year_level.folder_id = mv_assessment_relation.folder_id
        AND lu_year_level.deleted_at IS NULL
    ) AS PageYearLevel,
    (
        SELECT GROUP_CONCAT(folder_code.code ORDER BY folder_code.code SEPARATOR ',')
        FROM folder_code
        WHERE folder_code.folder_id = mv_assessment_relation.folder_id
        AND folder_code.deleted_at IS NULL
    ) AS ClassCode,

    COALESCE(course_specification.folder_id, course_imp_spec.folder_id) AS CourseFolderId,
    COALESCE(course_spec_folder.name, course_imp_spec_folder.name) AS CourseName,
    COALESCE(course_specification.subject_code, course_imp_spec.subject_code) AS SubjectCode,
    COALESCE(course_unit_specification.folder_id, course_unit_implementation_out.folder_id) AS UnitFolderId,
    COALESCE(unit_spec_folder.name, unit_imp_folder.name) AS UnitName,

    lu_work_type.name AS WorkType,
    CONCAT_WS(
        '; ',
        'Staff',
        IF(lu_work_type.is_visible_to_learners = 1, 'Students', NULL),
        IF(lu_work_type.is_visible_to_parents = 1, 'Parents', NULL)
    ) AS ActivityAudience,

    CASE COALESCE(
        submission_box.mark_visibility,
        project.mark_visibility,
        assess.mark_visibility,
        lti_assessment.mark_visibility,
        reporting.mark_visibility
    )
        WHEN 'staffOnly' THEN 'Staff Only'
        WHEN 'staffAndStudent' THEN 'Staff & Student'
        WHEN 'staffStudentAndParent' THEN 'Staff, Student & Parent'
        WHEN 'staffAndParent' THEN 'Staff & Parent'
        WHEN 0 THEN 'Staff Only'
        WHEN 1 THEN 'Staff & Student'
        WHEN 2 THEN 'Staff, Student & Parent'
        WHEN 3 THEN 'Staff & Parent'
        WHEN 4 THEN 'Staff & Parent'
        ELSE 'Not Applicable'
    END AS MarkVisibility,

    CASE
        WHEN project.id IS NOT NULL THEN IF(project.require_all_activities = 1, 'True', 'False')
        ELSE 'Not Applicable'
    END AS RequireAllActivities,

    CASE
        WHEN project.id IS NOT NULL THEN
            CASE project.summator
                WHEN 'min' THEN 'Minimum'
                WHEN 'max' THEN 'Maximum'
                WHEN 'avg' THEN 'Average'
                WHEN 'weighted' THEN 'Weighted average'
                ELSE 'False'
            END
        ELSE 'Not Applicable'
    END AS SummationMethod,

    CASE
        WHEN submission_box.id IS NOT NULL THEN IF(assessment.course_managed = 1, 'True', 'False')
        ELSE 'Not Applicable'
    END AS CommonAssessment,

    CASE
        WHEN submission_box.id IS NOT NULL THEN
            CASE
                WHEN submission_box.multi_submission = 1 THEN 'Multiple'
                WHEN submission_box.accept_submission = 1 THEN 'Single'
                ELSE 'None'
            END
        ELSE 'Not Applicable'
    END AS AcceptOnlineSubmissions,

    CASE
        WHEN submission_box.id IS NOT NULL THEN IF(submission_box.similarity_check_enabled = 1, 'True', 'False')
        ELSE 'Not Applicable'
    END AS SimilarityCheck,

    CASE
        WHEN project.id IS NOT NULL THEN COALESCE(project_input_mark_type_table.hr_name, 'False')
        WHEN assess.assess_id IS NOT NULL THEN COALESCE(quiz_mark_type_table.hr_name, 'False')
        WHEN reporting.id IS NOT NULL THEN COALESCE(reporting_input_mark_type_table.hr_name, 'False')
        WHEN submission_box.id IS NOT NULL THEN COALESCE(submission_box_input_mark_type_table.hr_name, 'False')
        WHEN lti_assessment.id IS NOT NULL THEN 'Not Applicable'
        ELSE 'Not Applicable'
    END AS InputMarkType,

    CASE
        WHEN project.id IS NOT NULL AND project_input_mark_type_table.hr_name = 'Letter Grade'
            THEN COALESCE(project_input_grade_group_table.name, 'False')
        WHEN assess.assess_id IS NOT NULL AND quiz_mark_type_table.hr_name = 'Letter Grade'
            THEN COALESCE(quiz_grade_group_table.name, 'False')
        WHEN reporting.id IS NOT NULL AND reporting_input_mark_type_table.hr_name = 'Letter Grade'
            THEN COALESCE(reporting_input_grade_group_table.name, 'False')
        WHEN submission_box.id IS NOT NULL AND submission_box_input_mark_type_table.hr_name = 'Letter Grade'
            THEN COALESCE(submission_box_input_grade_group_table.name, 'False')
        WHEN project.id IS NOT NULL
            OR assess.assess_id IS NOT NULL
            OR reporting.id IS NOT NULL
            OR submission_box.id IS NOT NULL
            THEN 'False'
        ELSE 'Not Applicable'
    END AS InputGradeGroup,

    CASE
        WHEN reporting.id IS NOT NULL THEN COALESCE(reporting_display_mark_type_table.hr_name, 'False')
        WHEN submission_box.id IS NOT NULL THEN COALESCE(submission_box_display_mark_type_table.hr_name, 'False')
        WHEN assess.assess_id IS NOT NULL THEN COALESCE(quiz_mark_type_table.hr_name, 'False')
        WHEN project.id IS NOT NULL THEN 'Not Applicable'
        WHEN lti_assessment.id IS NOT NULL THEN 'Not Applicable'
        ELSE 'Not Applicable'
    END AS DisplayMarkType,

    CASE
        WHEN reporting.id IS NOT NULL AND reporting_display_mark_type_table.hr_name = 'Letter Grade'
            THEN COALESCE(reporting_display_grade_group_table.name, 'False')
        WHEN submission_box.id IS NOT NULL AND submission_box_display_mark_type_table.hr_name = 'Letter Grade'
            THEN COALESCE(submission_box_display_grade_group_table.name, 'False')
        WHEN assess.assess_id IS NOT NULL AND quiz_mark_type_table.hr_name = 'Letter Grade'
            THEN COALESCE(quiz_grade_group_table.name, 'False')
        WHEN reporting.id IS NOT NULL
            OR submission_box.id IS NOT NULL
            OR assess.assess_id IS NOT NULL
            THEN 'False'
        ELSE 'Not Applicable'
    END AS DisplayGradeGroup,

    CASE
        WHEN reporting.id IS NOT NULL THEN IF(reporting.feedback_comments_enable = 1, 'True', 'False')
        ELSE 'Not Applicable'
    END AS UseComments,

    COALESCE(rubric_counts.rubric_count, 0) AS Rubric,
    assessment.weight AS Weight,

CASE
    WHEN assessment.original_assessment_id IS NOT NULL
        THEN CONCAT('Custom participation - ', assessment.original_assessment_id)
    WHEN course_specification.folder_id IS NOT NULL
        OR course_unit_specification.folder_id IS NOT NULL
        THEN 'Course'
    WHEN course_implementation.folder_id IS NOT NULL
        OR course_unit_implementation_out.folder_id IS NOT NULL
        THEN 'Class Imported'
    ELSE 'Class Created'
END AS SpecificationImplementation,

    assessment.publish_date AS PublishDate,
    assessment.due_date AS DueDate,

    CASE
        WHEN submission_box.id IS NOT NULL THEN submission_box.self_reviewability
        ELSE 'Not Applicable'
    END AS SelfReviewability,

    CASE
        WHEN submission_box.id IS NOT NULL THEN IF(submission_box.enable_policy = 1, 'True', 'False')
        ELSE 'Not Applicable'
    END AS Authenticity,

    CASE
        WHEN submission_box.id IS NOT NULL THEN COALESCE(
            NULLIF(
                CONCAT_WS(
                    ';',
                    IF(submission_box.enable_worksheet = 1, 'worksheet', NULL),
                    IF(submission_box.enable_offline = 1, 'offline', NULL),
                    IF(submission_box.enable_text = 1, 'text', NULL),
                    IF(submission_box.enable_files = 1, 'files', NULL)
                ),
                ''
            ),
            'No Submission'
        )
        ELSE 'Not Applicable'
    END AS SubmissionOptions,

    IF(
        COALESCE(
            course_specification.deleted_at,
            course_implementation.deleted_at,
            course_unit_specification.deleted_at,
            course_unit_implementation.deleted_at,
            unit_spec_folder.deleted_at,
            unit_imp_folder.deleted_at,
            course_imp_spec_folder.deleted_at,
            course_spec_folder.deleted_at
        ),
        'True',
        'False'
    ) AS ParentObjectDeleted

FROM mv_assessment_relation
JOIN assessment ON mv_assessment_relation.assessment_id = assessment.id
JOIN folder ON mv_assessment_relation.folder_id = folder.id
LEFT JOIN lu_work_type ON assessment.work_type_id = lu_work_type.id

LEFT JOIN submission_box ON assessment.id = submission_box.assessment_id
LEFT JOIN project ON assessment.id = project.assessment_id AND project.deleted_at IS NULL
LEFT JOIN assess ON assessment.id = assess.assessment_id AND assess.deleted_at IS NULL
LEFT JOIN reporting ON assessment.id = reporting.assessment_id AND reporting.deleted_at IS NULL
LEFT JOIN lti_assessment ON assessment.id = lti_assessment.assessment_id AND assessment.deleted_at IS NULL

LEFT JOIN (
    SELECT
        rubric_instance.assessment_id,
        COUNT(*) AS rubric_count
    FROM rubric_instance
    WHERE rubric_instance.deleted_at IS NULL
    GROUP BY rubric_instance.assessment_id
) rubric_counts ON assessment.id = rubric_counts.assessment_id

LEFT JOIN submission_mark_type submission_box_input_mark_type_table
    ON submission_box.instructor_mark_type = submission_box_input_mark_type_table.mark_type_id
LEFT JOIN submission_grade_group submission_box_input_grade_group_table
    ON submission_box.submission_instructor_grade_group_id = submission_box_input_grade_group_table.id
LEFT JOIN submission_mark_type submission_box_display_mark_type_table
    ON submission_box.mark_type = submission_box_display_mark_type_table.mark_type_id
LEFT JOIN submission_grade_group submission_box_display_grade_group_table
    ON submission_box.submission_grade_group_id = submission_box_display_grade_group_table.id

LEFT JOIN submission_mark_type project_input_mark_type_table
    ON project.mark_type_id = project_input_mark_type_table.mark_type_id
LEFT JOIN submission_grade_group project_input_grade_group_table
    ON project.grade_group_id = project_input_grade_group_table.id

LEFT JOIN submission_mark_type quiz_mark_type_table
    ON assess.mark_type_id = quiz_mark_type_table.mark_type_id
LEFT JOIN submission_grade_group quiz_grade_group_table
    ON assess.grade_group_id = quiz_grade_group_table.id

LEFT JOIN submission_mark_type reporting_input_mark_type_table
    ON reporting.instructor_mark_type = reporting_input_mark_type_table.mark_type_id
LEFT JOIN submission_grade_group reporting_input_grade_group_table
    ON reporting.instructor_grade_group_id = reporting_input_grade_group_table.id
LEFT JOIN submission_mark_type reporting_display_mark_type_table
    ON reporting.mark_type = reporting_display_mark_type_table.mark_type_id
LEFT JOIN submission_grade_group reporting_display_grade_group_table
    ON reporting.grade_group_id = reporting_display_grade_group_table.id

LEFT JOIN assessment_course_unit_implementation
    ON mv_assessment_relation.assessment_id = assessment_course_unit_implementation.assessment_id
LEFT JOIN course_specification
    ON mv_assessment_relation.course_specification_id = course_specification.id
LEFT JOIN course_implementation
    ON mv_assessment_relation.course_implementation_id = course_implementation.id
LEFT JOIN course_specification course_imp_spec
    ON course_implementation.course_specification_id = course_imp_spec.id
LEFT JOIN course_unit_specification
    ON mv_assessment_relation.course_unit_specification_id = course_unit_specification.id
LEFT JOIN course_unit_implementation
    ON mv_assessment_relation.course_unit_implementation_id = course_unit_implementation.id
LEFT JOIN course_unit_specification course_unit_implementation_out
    ON course_unit_implementation.course_unit_specification_id = course_unit_implementation_out.id
LEFT JOIN folder unit_spec_folder
    ON course_unit_specification.folder_id = unit_spec_folder.id
LEFT JOIN folder unit_imp_folder
    ON course_unit_implementation_out.folder_id = unit_imp_folder.id
LEFT JOIN folder course_imp_spec_folder
    ON course_imp_spec.folder_id = course_imp_spec_folder.id
LEFT JOIN folder course_spec_folder
    ON course_specification.folder_id = course_spec_folder.id

WHERE mv_assessment_relation.folder_id IN ($folderIds)
AND assessment.deleted_at IS NULL
AND NOT EXISTS (
    SELECT 1
    FROM task
    WHERE task.assessment_id = assessment.id
    AND task.deleted_at IS NULL
)
AND NOT EXISTS (
    SELECT 1
    FROM lesson_plan
    WHERE lesson_plan.assessment_id = assessment.id
    AND lesson_plan.deleted_at IS NULL
)
AND (
    project.id IS NOT NULL
    OR lti_assessment.id IS NOT NULL
    OR assess.assess_id IS NOT NULL
    OR reporting.id IS NOT NULL
    OR submission_box.id IS NOT NULL
)";
    }

    private function getRubricAuditQuery(string $folderIds): string
    {
        return "
SELECT
    assessment.id AS AssessmentId,
    assessment.title AS AssessmentTitle,
    mv_assessment_relation.folder_id AS FolderId,
    folder.name AS FolderName,
    (
        SELECT GROUP_CONCAT(DISTINCT lu_year_level.name ORDER BY lu_year_level.sequence SEPARATOR ',')
        FROM folder_year_level
        JOIN lu_year_level ON folder_year_level.year_level_id = lu_year_level.id
        WHERE folder_year_level.folder_id = mv_assessment_relation.folder_id
        AND lu_year_level.deleted_at IS NULL
    ) AS PageYearLevel,
    (
        SELECT GROUP_CONCAT(folder_code.code ORDER BY folder_code.code SEPARATOR ',')
        FROM folder_code
        WHERE folder_code.folder_id = mv_assessment_relation.folder_id
        AND folder_code.deleted_at IS NULL
    ) AS ClassCode,
    COALESCE(course_specification.folder_id, course_imp_spec.folder_id) AS CourseFolderId,
    COALESCE(course_spec_folder.name, course_imp_spec_folder.name) AS CourseName,
    COALESCE(course_specification.subject_code, course_imp_spec.subject_code) AS SubjectCode,
    COALESCE(course_unit_specification.folder_id, course_unit_implementation_out.folder_id) AS UnitFolderId,
    COALESCE(unit_spec_folder.name, unit_imp_folder.name) AS UnitName,
    lu_work_type.name AS WorkType,
    rubric_instance.id AS RubricID,
    rubric_instance.title AS RubricTitle,
    rubric_instance.weight AS RubricWeight,
    rubric_mark_type_table.hr_name AS CapabilityMarkType,
    CASE
        WHEN rubric_mark_type_table.hr_name = 'Letter Grade'
            THEN rubric_grade_group_table.name
        ELSE 'Null'
    END AS CapabilityGradeGroup

FROM rubric_instance
JOIN assessment ON rubric_instance.assessment_id = assessment.id
JOIN mv_assessment_relation ON assessment.id = mv_assessment_relation.assessment_id
JOIN folder ON mv_assessment_relation.folder_id = folder.id
LEFT JOIN lu_work_type ON assessment.work_type_id = lu_work_type.id

LEFT JOIN submission_mark_type rubric_mark_type_table
    ON rubric_instance.rubric_capability_mark_type_id = rubric_mark_type_table.mark_type_id
LEFT JOIN submission_grade_group rubric_grade_group_table
    ON rubric_instance.rubric_capability_grade_group_id = rubric_grade_group_table.id

LEFT JOIN assessment_course_unit_implementation
    ON mv_assessment_relation.assessment_id = assessment_course_unit_implementation.assessment_id
LEFT JOIN course_specification
    ON mv_assessment_relation.course_specification_id = course_specification.id
LEFT JOIN course_implementation
    ON mv_assessment_relation.course_implementation_id = course_implementation.id
LEFT JOIN course_specification course_imp_spec
    ON course_implementation.course_specification_id = course_imp_spec.id
LEFT JOIN course_unit_specification
    ON mv_assessment_relation.course_unit_specification_id = course_unit_specification.id
LEFT JOIN course_unit_implementation
    ON mv_assessment_relation.course_unit_implementation_id = course_unit_implementation.id
LEFT JOIN course_unit_specification course_unit_implementation_out
    ON course_unit_implementation.course_unit_specification_id = course_unit_implementation_out.id
LEFT JOIN folder unit_spec_folder
    ON course_unit_specification.folder_id = unit_spec_folder.id
LEFT JOIN folder unit_imp_folder
    ON course_unit_implementation_out.folder_id = unit_imp_folder.id
LEFT JOIN folder course_imp_spec_folder
    ON course_imp_spec.folder_id = course_imp_spec_folder.id
LEFT JOIN folder course_spec_folder
    ON course_specification.folder_id = course_spec_folder.id

LEFT JOIN submission_box ON assessment.id = submission_box.assessment_id
LEFT JOIN project ON assessment.id = project.assessment_id AND project.deleted_at IS NULL
LEFT JOIN assess ON assessment.id = assess.assessment_id AND assess.deleted_at IS NULL
LEFT JOIN reporting ON assessment.id = reporting.assessment_id AND reporting.deleted_at IS NULL
LEFT JOIN lti_assessment ON assessment.id = lti_assessment.assessment_id AND assessment.deleted_at IS NULL

WHERE mv_assessment_relation.folder_id IN ($folderIds)
AND assessment.deleted_at IS NULL
AND rubric_instance.deleted_at IS NULL
AND NOT EXISTS (
    SELECT 1
    FROM task
    WHERE task.assessment_id = assessment.id
    AND task.deleted_at IS NULL
)
AND NOT EXISTS (
    SELECT 1
    FROM lesson_plan
    WHERE lesson_plan.assessment_id = assessment.id
    AND lesson_plan.deleted_at IS NULL
)
AND (
    project.id IS NOT NULL
    OR lti_assessment.id IS NOT NULL
    OR assess.assess_id IS NOT NULL
    OR reporting.id IS NOT NULL
    OR submission_box.id IS NOT NULL
)";
    }
}
