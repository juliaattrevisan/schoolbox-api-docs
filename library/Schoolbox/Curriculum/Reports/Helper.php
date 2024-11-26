<?php

namespace Schoolbox\Curriculum\Reports;

use Alaress\Database\Connector\IConnector;
use Schoolbox\Curriculum\Node\Mapper as CurriculumMapper;
use Schoolbox\Assessment\Rubric\DataModel\Capability\Mapper as RubricCapabilityMapper;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;

class Helper
{
    public function __construct(
        private CurriculumMapper $curriculumMapper,
        private RubricCapabilityMapper $rubricCapabilityMapper,
        private AssessmentMapper $assessmentMapper,
    ) {
    }

    /**
     * Getting individual usage of curriculum in each section
     *
     * @param int $nodeId
     * @param int $courseId
     * @param int $unitId
     * @return array
     */
    public function getUsage(
        int $nodeId,
        int $courseId,
        int $unitId
    ): array {

        $db = $this->curriculumMapper->getDatabase();

        $unitUsage = $this->getUnitUsage($db, $nodeId, $courseId, $unitId);

        $assessmentUsage = $this->getAssessmentUsage($db, $nodeId, $courseId, $unitId);

        $rubricUsage = $this->getRubricUsage($db, $nodeId, $courseId, $unitId);

        $inUseRubricUsage = $this->getInUseRubricUsage($db, $nodeId, $courseId, $unitId);

        return array_merge(
            $unitUsage,
            $assessmentUsage,
            $rubricUsage,
            $inUseRubricUsage
        );
    }

    /**
     * Getting unit usage
     *
     * @param IConnector $db
     * @param int $nodeId
     * @param int $courseId
     * @param int $unitId
     * @return array
     */
    private function getUnitUsage(
        IConnector $db,
        int $nodeId,
        int $courseId,
        int $unitId
    ): array {
        $statement = $db->prepareQuery(
            "
            SELECT :type type, cus.course_specification_id id, f.name title, cus.folder_id
            FROM course_unit_specification cus
            INNER JOIN curriculum_node_for_category cnfc
                ON cnfc.category_id = cus.folder_id
                AND cnfc.curriculum_node_id = :nodeId
            INNER JOIN folder f ON f.id = cus.folder_id
            WHERE cus.course_specification_id = :courseId AND cus.folder_id = :unitId
            ",
            [
                'type' => Cursor::COURSE_UNIT,
                'nodeId' => $nodeId,
                'courseId' => $courseId,
                'unitId' => $unitId
            ]
        );

        $statement->execute();
        $usage = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $usage[$row['type']] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'folderId' => $row['folder_id'],
            ];
        }
        return $usage;
    }

    /**
     * Getting assessment usage include all types of assessments
     *
     * @param IConnector $db
     * @param int $nodeId
     * @param int $courseId
     * @param int $unitId
     * @return array
     */
    private function getAssessmentUsage(
        IConnector $db,
        int $nodeId,
        int $courseId,
        int $unitId
    ): array {
        $activityUsageQuery =
            "
            SELECT
                :type type, activity.type assessmentType, a.id, a.title, cus.folder_id
            FROM (
                 SELECT :task type, t.assessment_id
                 FROM task t
                 UNION
                 SELECT :quiz type, a.assessment_id
                 FROM assess a
                 UNION
                 SELECT :lti type, lti.assessment_id
                 FROM lti_assessment lti
                 UNION
                 SELECT :dueWork type, sbox.assessment_id
                 FROM submission_box sbox
                 UNION
                 SELECT :project type, p.assessment_id
                 FROM project p
            ) activity
            INNER JOIN curriculum_node_for_assessment cnfa
                ON cnfa.assessment_id = activity.assessment_id
                AND cnfa.curriculum_node_id = :nodeId
            INNER JOIN assessment_course_unit_specification acus ON acus.assessment_id = cnfa.assessment_id
            INNER JOIN course_unit_specification cus
                ON cus.id = acus.course_unit_specification_id
                AND cus.course_specification_id = :courseId
                AND cus.folder_id = :unitId
            INNER JOIN assessment a
                ON a.id = activity.assessment_id
                AND a.deleted_at IS NULL
        ";

        $lessonPlanUsageQuery =
            "
            SELECT
                :lpType type, :lpType assessmentType, a.id, a.title, cus.folder_id
            FROM lesson_plan lp
            INNER JOIN curriculum_node_for_assessment cnfa
                ON cnfa.assessment_id = lp.assessment_id
                AND cnfa.curriculum_node_id = :nodeId
            INNER JOIN assessment_course_unit_specification acus ON acus.assessment_id = cnfa.assessment_id
            INNER JOIN course_unit_specification cus
                ON cus.id = acus.course_unit_specification_id
                AND cus.course_specification_id = :courseId
                AND cus.folder_id = :unitId
            INNER JOIN assessment a
                ON a.id = lp.assessment_id
                AND a.deleted_at IS NULL
        ";

        $combinedUsageQuery = '( ' . implode(' UNION ', [
                $activityUsageQuery,
                $lessonPlanUsageQuery,
            ]) . ' )';


        $param = [
            'type' => Cursor::COURSE_ACTIVITY,
            'task' => Cursor::COURSE_ACTIVITY_TASK,
            'quiz' => Cursor::COURSE_ACTIVITY_QUIZ,
            'lti' => Cursor::COURSE_ACTIVITY_LTI,
            'dueWork' => Cursor::COURSE_ACTIVITY_DUE_WORK,
            'project' => Cursor::COURSE_ACTIVITY_PROJECT,
            'lpType' => Cursor::COURSE_ACTIVITY_LESSON_PLAN,
            'nodeId' => $nodeId,
            'courseId' => $courseId,
            'unitId' => $unitId,
        ];

        $statement = $db->prepareQuery($combinedUsageQuery, $param);
        $statement->execute();
        $usage = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $usage[$row['type']][$row['id']] = [
                'id' => $row['id'],
                'icon' => Cursor::ACTIVITY_ICON_LIST[$row['assessmentType']],
            ];
        }

        return $usage;
    }

    /**
     * Getting usage for the curriculum node in rubrics for both course and unit
     *
     * @param IConnector $db
     * @param int $nodeId
     * @param int $courseId
     * @param int $unitId
     * @return array
     */
    private function getRubricUsage(
        IConnector $db,
        int $nodeId,
        int $courseId,
        int $unitId
    ): array {
        $statement = $db->prepareQuery(
            "
            SELECT
                c.type,
                rc.id capId,
                rc.name capName,
                r.id indId,
                r.name indName,
                c.folder_id,
                c.parentId
            FROM rubric_capability rc
            LEFT JOIN curriculum_node_for_rubric_capability cnrc
                ON (rc.id = cnrc.rubric_capability_id AND cnrc.curriculum_node_id = :nodeId)
            LEFT JOIN (
                SELECT *
                FROM rubric_indicator ri
                INNER JOIN curriculum_node_for_rubric_indicator cnri
                    ON (ri.id = cnri.rubric_indicator_id AND cnri.curriculum_node_id = :nodeId)
            ) r ON rc.id = r.rubric_capability_id
            INNER JOIN (
                SELECT :courseSpecType type, cs.folder_id parentId, cus.folder_id, cs.id
                FROM course_specification cs
                INNER JOIN course_unit_specification cus ON cus.course_specification_id = cs.id
                UNION
                SELECT :courseUnitType type, cus.folder_id parentId, cus.folder_id, cus.course_specification_id id
                FROM course_unit_specification cus
            ) c ON (c.parentId = rc.parent_id AND c.folder_id = :unitId AND c.id = :courseId)
            WHERE
                (cnrc.curriculum_node_id IS NOT NULL OR r.curriculum_node_id IS NOT NULL)
                AND rc.deleted_at IS NULL AND r.deleted_at IS NULL
            ",
            [
                'nodeId' => $nodeId,
                'courseSpecType' => Cursor::COURSE_RUBRIC_CAPABILITY,
                'courseUnitType' => Cursor::COURSE_UNIT_RUBRIC_CAPABILITY,
                'unitId' => $unitId,
                'courseId' => $courseId,
            ]
        );

        $statement->execute();
        $usage = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $usage[$row['type']]['folderId'] = $row['parentId'];
            $usage[$row['type']][$row['capId']]['capabilities'] = $row['capName'];
            if ($row['indName'] === null) {
                continue;
            }
            $usage[$row['type']][$row['capId']]['indicators'][] = [
                'name' => $row['indName']
            ];
        }
        return $usage;
    }

    /**
     * Get usage for the due works that uses the rubrics that contain the curriculum node
     *
     * @param IConnector $db
     * @param int $nodeId
     * @param int $courseId
     * @param int $unitId
     * @return array
     */
    private function getInUseRubricUsage(
        IConnector $db,
        int $nodeId,
        int $courseId,
        int $unitId
    ): array {
        $statement = $db->prepareQuery(
            "
            SELECT
                :type type,
                v.assessment_id,
                v.assessment_title,
                cnrc.rubric_capability_id,
                cnri.rubric_indicator_id,
                v.capability_id,
                v.capability_name,
                v.indicator_id,
                v.indicator_name
            FROM v_rubric v
            LEFT JOIN curriculum_node_for_rubric_capability cnrc
                ON cnrc.rubric_capability_id = v.capability_id
                AND cnrc.curriculum_node_id = :nodeId
            LEFT JOIN curriculum_node_for_rubric_indicator cnri
                ON cnri.rubric_indicator_id = v.indicator_id
                AND cnri.curriculum_node_id = :nodeId
            INNER JOIN assessment_course_unit_specification acus
                ON v.assessment_id = acus.assessment_id
            INNER JOIN course_unit_specification cus
                ON cus.id = acus.course_unit_specification_id
                AND cus.course_specification_id = :courseId
            WHERE
                cus.folder_id = :unitId
                AND (cnrc.curriculum_node_id IS NOT NULL OR cnri.curriculum_node_id IS NOT NULL)
        ",
            [
                'type' => Cursor::COURSE_IN_USE_RUBRIC,
                ':nodeId' => $nodeId,
                ':courseId' => $courseId,
                ':unitId' => $unitId
            ]
        );

        $statement->execute();
        $usage = [];
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $usage[$row['type']][$row['assessment_id']][$row['capability_id']]['name'] = $row['capability_name'];
            if ($row['rubric_indicator_id'] === null) {
                continue;
            }
            $usage[$row['type']][$row['assessment_id']][$row['capability_id']]['indicators'][] = [
                'name' => $row['indicator_name']
            ];
        }
        return $usage;
    }

    public function findModelsForResults(array $usage): array
    {
        $result = [];
        $result[Cursor::COURSE_RUBRIC_CAPABILITY] =
            $this->rubricCapabilityMapper->findByColumn(
                'id',
                array_keys($usage[Cursor::COURSE_RUBRIC_CAPABILITY] ?? []),
            )->reindex();

        $result[Cursor::COURSE_UNIT_RUBRIC_CAPABILITY] =
            $this->rubricCapabilityMapper->findByColumn(
                'id',
                array_keys($usage[Cursor::COURSE_UNIT_RUBRIC_CAPABILITY] ?? []),
            )->reindex();

        $result[Cursor::COURSE_ACTIVITY] =
            $this->assessmentMapper->findByColumn(
                'id',
                array_keys($usage[Cursor::COURSE_ACTIVITY] ?? []),
            )->reindex();

        $result[Cursor::COURSE_ACTIVITY_LESSON_PLAN] =
            $this->assessmentMapper->findByColumn(
                'id',
                array_keys($usage[Cursor::COURSE_ACTIVITY_LESSON_PLAN] ?? []),
            )->reindex();

        $result[Cursor::COURSE_IN_USE_RUBRIC] =
            $this->assessmentMapper->findByColumn(
                'id',
                array_keys($usage[Cursor::COURSE_IN_USE_RUBRIC] ?? []),
            )->reindex();

        $inUseCap = [];
        $inUse = $usage[Cursor::COURSE_IN_USE_RUBRIC] ?? [];
        array_walk(
            $inUse,
            function ($item) use (&$inUseCap) {
                $inUseCap = array_merge($inUseCap, array_keys($item));
            }
        );
        $result['InUseCap'] =
            $this->rubricCapabilityMapper->findByColumn(
                'id',
                $inUseCap
            )->reindex();

        return $result;
    }
}
