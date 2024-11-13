<?php

namespace Schoolbox\Curriculum\Reports;

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
            SELECT '%s' type, f.id, f.name title
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
                'activity' type, a.id, a.title
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
                activity.type, a.id, a.title
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
                $lessonPlanUsageQuery
        ]) . ' )';

        $statement = $db->executeQuery($combinedUsageQuery);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $usage[$row['type']][$row['id']] = $row['title'];
        }

        return $usage;
    }
}
