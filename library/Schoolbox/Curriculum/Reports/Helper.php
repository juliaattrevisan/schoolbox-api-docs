<?php

namespace Schoolbox\Curriculum\Reports;

use Schoolbox\Assessment\Rubric\DataModel\Capability\Mapper;
use Schoolbox\Curriculum\Node\Mapper as CurriculumMapper;

class Helper
{
    public function __construct(
        private CurriculumMapper $curriculumMapper,
    ) {
    }

    public function getUsage(
        int $nodeId,
        int $courseId,
        int $unitId
    ): array {

        $db = $this->curriculumMapper->getDatabase();

        $usage = [];

        $unitUsageQuery = sprintf(
            "
            SELECT '%s' type, cus.course_specification_id id, f.name title, cus.folder_id
            FROM course_unit_specification cus
            INNER JOIN curriculum_node_for_category cnfc
                ON cnfc.category_id = cus.folder_id
                AND cnfc.curriculum_node_id = %d
            INNER JOIN folder f ON f.id = cus.folder_id
            WHERE cus.course_specification_id = %d AND cus.folder_id = %d
            ",
            Cursor::COURSE_UNIT,
            $nodeId,
            $courseId,
            $unitId
        );

        $activityUsageQuery = sprintf(
            "
            SELECT
                'activity' type, a.id, a.title, cus.folder_id
            FROM (
                 SELECT '%s' type, t.assessment_id
                 FROM task t
                 UNION
                 SELECT '%s' type, a.assessment_id
                 FROM assess a
                 UNION
                 SELECT '%s' type, lti.assessment_id
                 FROM lti_assessment lti
                 UNION
                 SELECT '%s' type, sbox.assessment_id
                 FROM submission_box sbox
                 UNION
                 SELECT '%s' type, p.assessment_id
                 FROM project p
            ) activity
            INNER JOIN curriculum_node_for_assessment cnfa
                ON cnfa.assessment_id = activity.assessment_id
                AND cnfa.curriculum_node_id = %d
            INNER JOIN assessment_course_unit_specification acus ON acus.assessment_id = cnfa.assessment_id
            INNER JOIN course_unit_specification cus
                ON cus.id = acus.course_unit_specification_id
                AND cus.course_specification_id = %d
                AND cus.folder_id = %d
            INNER JOIN assessment a
                ON a.id = activity.assessment_id
                AND a.deleted_at IS NULL
        ",
            Cursor::COURSE_ACTIVITY_TASK,
            Cursor::COURSE_ACTIVITY_QUIZ,
            Cursor::COURSE_ACTIVITY_LTI,
            Cursor::COURSE_ACTIVITY_DUE_WORK,
            Cursor::COURSE_ACTIVITY_PROJECT,
            $nodeId,
            $courseId,
            $unitId
        );

        $lessonPlanUsageQuery = sprintf(
            "
            SELECT
                activity.type, a.id, a.title, cus.folder_id
            FROM (
                 SELECT '%s' type, lp.assessment_id
                 FROM lesson_plan lp
            ) activity
            INNER JOIN curriculum_node_for_assessment cnfa
                ON cnfa.assessment_id = activity.assessment_id
                AND cnfa.curriculum_node_id = %d
            INNER JOIN assessment_course_unit_specification acus ON acus.assessment_id = cnfa.assessment_id
            INNER JOIN course_unit_specification cus
                ON cus.id = acus.course_unit_specification_id
                AND cus.course_specification_id = %d
                AND cus.folder_id = %d
            INNER JOIN assessment a
                ON a.id = activity.assessment_id
                AND a.deleted_at IS NULL
        ",
            Cursor::COURSE_ACTIVITY_LESSON_PLAN,
            $nodeId,
            $courseId,
            $unitId
        );

        $combinedUsageQuery = '( ' . implode(' UNION ', [
                $unitUsageQuery,
                $activityUsageQuery,
                $lessonPlanUsageQuery,
            ]) . ' )';

        $statement = $db->executeQuery($combinedUsageQuery);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $usage[$row['type']][] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'folder_id' => $row['folder_id'],
            ];
        }

        $rubricUsageQuery = sprintf(
            "
            SELECT c.type, rc.id capId, rc.name capName, r.id indId, r.name indName, c.folder_id, c.parentId
            FROM rubric_capability rc
            LEFT JOIN curriculum_node_for_rubric_capability cnrc
                ON (rc.id = cnrc.rubric_capability_id AND cnrc.curriculum_node_id = %d)
            LEFT JOIN (
                SELECT *
                FROM rubric_indicator ri
                INNER JOIN curriculum_node_for_rubric_indicator cnri
                    ON (ri.id = cnri.rubric_indicator_id AND cnri.curriculum_node_id = %d)
            ) r ON rc.id = r.rubric_capability_id
            INNER JOIN (
                SELECT '%s' type, cs.folder_id parentId, cus.folder_id, cs.id
                FROM course_specification cs
                INNER JOIN course_unit_specification cus ON cus.course_specification_id = cs.id
                UNION
                SELECT '%s' type, cus.folder_id parentId, cus.folder_id, cus.course_specification_id id
                FROM course_unit_specification cus
            ) c ON (c.parentId = rc.parent_id AND c.folder_id = %d AND c.id = %d)
            WHERE
                (cnrc.curriculum_node_id IS NOT NULL OR r.curriculum_node_id IS NOT NULL)
                AND rc.deleted_at IS NULL AND r.deleted_at IS NULL
            ",
            $nodeId,
            $nodeId,
            Cursor::COURSE_RUBRIC_CAPABILITY,
            Cursor::COURSE_UNIT_RUBRIC_CAPABILITY,
            $unitId,
            $courseId,
        );

        $statement = $db->executeQuery($rubricUsageQuery);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $usage[$row['type']]['folderId'] = $row['parentId'];
            if ($row['indName'] === null) {
                continue;
            }
            $usage[$row['type']][$row['capId']]['indicators'][] = [
                'name' => $row['indName']
            ];
        }

        return $usage;
    }
}
