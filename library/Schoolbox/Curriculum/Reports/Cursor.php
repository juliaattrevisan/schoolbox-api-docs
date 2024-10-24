<?php

namespace Schoolbox\Curriculum\Reports;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Alaress\Database\Predicate\Builder;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Mapper as CurriculumMapper;
use ValueError;

class Cursor implements ICursor
{
    /**
     * Minimum number of results returnable from the cursor at once:
     * it must return at least one result.
     */
    public const LIMIT_MIN = 1;

    /**
     * Maximum number of results returnable from the cursor at once:
     * IMO 100 results is a sensible maximum.
     */
    public const LIMIT_MAX = 100;

    /**
     * A sensible default number of results to return from the cursor at once:
     * IMO the maximum is a sensible default.
     */
    public const LIMIT_DEFAULT = self::LIMIT_MAX;

    public const COURSE_COURSE_SPECIFICATION = 'courseSpecification';
    public const COURSE_UNIT = 'unit';
    public const COURSE_ACTIVITY_TASK = 'task';
    public const COURSE_ACTIVITY_QUIZ = 'quiz';
    public const COURSE_ACTIVITY_LTI = 'LTI';
    public const COURSE_ACTIVITY_DUE_WORK = 'dueWork';
    public const COURSE_ACTIVITY_PROJECT = 'project';
    public const COURSE_ACTIVITY_LESSON_PLAN = 'lessonPlan';

    public function __construct(
        private CurriculumMapper $curriculumMapper,
        private array $courseId,
        private array $nodeId,
        private int $limit,
        private ?ICodec $codec = null,
    ) {
        $this->codec ??= new Base64();
        if ($this->limit < self::LIMIT_MIN) {
            throw new ValueError(sprintf('Limit must be at least %d: %d provided', self::LIMIT_MIN, $this->limit));
        }
        if ($this->limit > self::LIMIT_MAX) {
            throw new ValueError(sprintf('Limit must be at most %d: %d provided', self::LIMIT_MAX, $this->limit));
        }
    }

    /**
     * @inheritDoc
     */
    public function next(Collection $collection): ?string
    {
        // As usual: if the collection is empty, there is no next
        if ($collection->count() === 0) {
            return null;
        }

        // Find the last item in the collection: get a pointer to that
        // Expect `$last` to be an array
        return $this->codec->encode($collection->last());
    }

    /**
     * @inheritDoc
     */
    public function current(Collection $collection): ?string
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function retrieveResults(?string $encodedCursor): Collection
    {
        $response = [];

        $nodes = [];

        foreach ($this->nodeId as $nodeId) {
            $test = $this->curriculumMapper->findById($nodeId);
            $collect = $this->curriculumMapper->loadDescendants($test);

            $collect = $this->curriculumMapper->getModelAsCollection($collect);

            $nodes[] = $collect;
        }

        $response['nodes'] = $nodes;

        return new Collection($response);
    }

    public function retrieveUsageCount(): array
    {
        $inCourseId = '(' . implode(',', $this->courseId) . ')';

        $db = $this->curriculumMapper->getDatabase();

        $counts = [];

        foreach ($this->nodeId as $nodeId) {
            $allLineageNodeIds = array_merge(
                [$nodeId],
                $this->curriculumMapper->findDescendantIds([$nodeId], 'curriculum_node_parent_id')
            );

            $nodesPlaceHolder = Builder::getArrayPlaceholders($allLineageNodeIds, 'nodes');
            $params           = Builder::getArrayParameters($allLineageNodeIds, 'nodes');

            $courseCountQuery = sprintf(
                "
                SELECT '%s' type, cnfc.curriculum_node_id, cs.id courseSpecId, null unitId, COUNT(*) count
                FROM course_specification cs
                INNER JOIN curriculum_node_for_category cnfc ON cs.folder_id = cnfc.category_id AND cs.id IN %s
                WHERE cnfc.curriculum_node_id IN %s
                GROUP BY cnfc.curriculum_node_id, cs.id
                ",
                self::COURSE_COURSE_SPECIFICATION,
                $inCourseId,
                $nodesPlaceHolder
            );

            $unitCountQuery = sprintf(
                "
                SELECT '%s' type, cnfc.curriculum_node_id, cus.course_specification_id courseSpecId, cus.folder_id unitId, COUNT(*) count
                FROM course_unit_specification cus
                INNER JOIN curriculum_node_for_category cnfc
                    ON cnfc.category_id = cus.folder_id AND cnfc.curriculum_node_id IN %s
                WHERE cus.course_specification_id IN %s
                GROUP BY cnfc.curriculum_node_id, cus.course_specification_id
            ",
                self::COURSE_UNIT,
                $nodesPlaceHolder,
                $inCourseId
            );

            $unitActivityCountQuery = sprintf(
                "
                SELECT
                    activity.type,
                    cnfa.curriculum_node_id,
                    cus.course_specification_id courseSpecId,
                    cus.folder_id as unitId,
                    COUNT(cnfa.curriculum_node_id) as count
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
                     UNION
                     SELECT '%s' type, lp.assessment_id
                     FROM lesson_plan lp
                ) activity
                INNER JOIN curriculum_node_for_assessment cnfa
                    ON cnfa.assessment_id = activity.assessment_id
                    AND cnfa.curriculum_node_id IN %s
                INNER JOIN assessment_course_unit_specification acus ON acus.assessment_id = cnfa.assessment_id
                INNER JOIN course_unit_specification cus
                        ON cus.id = acus.course_unit_specification_id
                        AND cus.course_specification_id IN %s
                GROUP BY type, cnfa.curriculum_node_id, cus.course_specification_id, cus.folder_id
            ",
                self::COURSE_ACTIVITY_TASK,
                self::COURSE_ACTIVITY_QUIZ,
                self::COURSE_ACTIVITY_LTI,
                self::COURSE_ACTIVITY_DUE_WORK,
                self::COURSE_ACTIVITY_PROJECT,
                self::COURSE_ACTIVITY_LESSON_PLAN,
                $nodesPlaceHolder,
                $inCourseId
            );

            $unitLessonPlanCountQuery = sprintf(
                "
                SELECT
                    activity.type,
                    cnfa.curriculum_node_id,
                    cus.course_specification_id courseSpecId,
                    cus.folder_id as unitId,
                    COUNT(cnfa.curriculum_node_id) as count
                FROM (
                     SELECT '%s' type, lp.assessment_id
                     FROM lesson_plan lp
                ) activity
                INNER JOIN curriculum_node_for_assessment cnfa
                    ON cnfa.assessment_id = activity.assessment_id
                    AND cnfa.curriculum_node_id IN %s
                INNER JOIN assessment_course_unit_specification acus ON acus.assessment_id = cnfa.assessment_id
                INNER JOIN course_unit_specification cus
                        ON cus.id = acus.course_unit_specification_id
                        AND cus.course_specification_id IN %s
                GROUP BY activity.type, cnfa.curriculum_node_id, cus.course_specification_id, cus.folder_id
            ",
                self::COURSE_ACTIVITY_LESSON_PLAN,
                $nodesPlaceHolder,
                $inCourseId
            );

            $megaQuery = '( ' .
                implode(
                    ' ) UNION ( ',
                    [
                        $courseCountQuery,
                        $unitCountQuery,
                        $unitActivityCountQuery,
                        $unitLessonPlanCountQuery
                    ]
                ) . ' )';

            $query = $db->executeQuery($megaQuery, $params);

            foreach ($query->fetchAll() as $row) {
                $type = $row['type'];
                if ($type === self::COURSE_COURSE_SPECIFICATION) {
                    $counts[$row['curriculum_node_id']][$row['courseSpecId']][$type] = $row['count'];
                } else {
                    $counts[$row['curriculum_node_id']][$row['courseSpecId']][$row['unitId']][$type] = $row['count'];
                }
            }
        }

        return $counts;
    }

    /**
     * @inheritdoc
     * @return ?int
     */
    public function retrieveResultCount(): ?int
    {
        /*I guess realistically speaking it should return the requested nodes as that's how it will
         * be used for iterating results, and each node has different number of descendants
         */
        return count($this->nodeId);
    }
}
