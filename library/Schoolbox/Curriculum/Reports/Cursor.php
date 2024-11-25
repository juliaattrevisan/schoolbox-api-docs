<?php

namespace Schoolbox\Curriculum\Reports;

use Alaress\Codec\ICodec;
use Alaress\Codec\Json\Base64;
use Alaress\Database\Predicate\Builder;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Mapper as CurriculumMapper;
use ValueError;
use InvalidArgumentException;

/**
 * Cursor for curriculum coverage report
 *
 * Fetches a list of curriculum nodes and the descendants, associate it with the specified course specifications
 * and its units to find the usage of the nodes within the courses.
 */
class Cursor implements ICursor
{
    /**
     * Minimum number of results returnable from the cursor at once:
     * it must return at least one result.
     */
    public const LIMIT_MIN = 1;

    /**
     * Maximum number of results returnable from the cursor at once:
     * IMO 3 results is a sensible maximum.
     */
    public const LIMIT_MAX = 3;

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
    public const COURSE_RUBRIC_CAPABILITY = 'rubricCapability';
    public const COURSE_UNIT_RUBRIC_CAPABILITY = 'unitRubricCapability';
    public const COURSE_RUBRIC_INDICATOR = 'rubricIndicator';
    public const COURSE_UNIT_RUBRIC_INDICATOR = 'unitRubricIndicator';
    public const COURSE_IN_USE_RUBRIC = 'inUseRubric';
    public const ACTIVITY_ICON_LIST = [
        self::COURSE_ACTIVITY_DUE_WORK => 'due-work',
        self::COURSE_ACTIVITY_TASK => 'task',
        self::COURSE_ACTIVITY_QUIZ => 'quiz',
        self::COURSE_ACTIVITY_LTI => 'lti',
        self::COURSE_ACTIVITY_PROJECT => 'project',
        self::COURSE_ACTIVITY_LESSON_PLAN => 'lesson-plan',
    ];

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

        $last = $collection->last();
        // Find the last item in the collection: get a pointer to that
        // Expect `$last` to be an array
        return $this->codec->encode(
            [
                'id' => $last->getId(),
                'courseId' => json_encode($this->courseId),
                'nodeId' => json_encode($this->nodeId),
            ]
        );
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
        $pb = $this->curriculumMapper->getPredicateBuilder();

        $pb->in('id', $this->nodeId);

        $idColumn = "{$this->curriculumMapper->getTable()}.{$this->curriculumMapper->getIdentifierColumn()}";

        $options = [
            'order' => [
                $idColumn => 'asc',
            ],
            'limit' => $this->limit
        ];

        if (null !== $encodedCursor) {
            $cursor = $this->codec->decode($encodedCursor);

            if (json_decode($cursor['courseId']) != $this->courseId) {
                throw new InvalidArgumentException('Course IDs supplied mismatched with the cursor given');
            }

            if (json_decode($cursor['nodeId']) != $this->nodeId) {
                throw new InvalidArgumentException(
                    'Curriculum node IDs supplied mismatched with the cursor given'
                );
            }

            $pb->greaterThan($idColumn, $cursor['id']);
        }

        $result = $this->curriculumMapper->findByPredicates($pb, $options);

        $result->map(function ($node) {
            return $this->curriculumMapper->getModelAsCollection(
                $this->curriculumMapper->loadDescendants($node)
            );
        });

        return $result;
    }

    public function retrieveUsageCount(): array
    {

        $db = $this->curriculumMapper->getDatabase();

        $counts = [];

        foreach ($this->nodeId as $nodeId) {
            $allLineageNodeIds = array_merge(
                [$nodeId],
                $this->curriculumMapper->findDescendantIds([$nodeId], 'curriculum_node_parent_id')
            );

            $nodesPlaceHolder = Builder::getArrayPlaceholders($allLineageNodeIds, 'nodes');
            $params           = Builder::getArrayParameters($allLineageNodeIds, 'nodes');

            $coursePlaceHolder = Builder::getArrayPlaceholders($this->courseId, 'courses');
            $params = array_merge(
                $params,
                Builder::getArrayParameters($this->courseId, 'courses')
            );

            $courseCountQuery = sprintf(
                "
                SELECT '%s' type, cnfc.curriculum_node_id, cs.id courseSpecId, null unitId, COUNT(*) count
                FROM course_specification cs
                INNER JOIN curriculum_node_for_category cnfc ON cs.folder_id = cnfc.category_id AND cs.id IN %s
                WHERE cnfc.curriculum_node_id IN %s
                GROUP BY cnfc.curriculum_node_id, cs.id
                ",
                self::COURSE_COURSE_SPECIFICATION,
                $coursePlaceHolder,
                $nodesPlaceHolder
            );

            $unitCountQuery = sprintf(
                "
                SELECT '%s' type, cnfc.curriculum_node_id, cus.course_specification_id courseSpecId, cus.folder_id unitId, COUNT(*) count
                FROM course_unit_specification cus
                INNER JOIN curriculum_node_for_category cnfc
                    ON cnfc.category_id = cus.folder_id AND cnfc.curriculum_node_id IN %s
                WHERE cus.course_specification_id IN %s
                GROUP BY cnfc.curriculum_node_id, cus.course_specification_id, cus.folder_id
            ",
                self::COURSE_UNIT,
                $nodesPlaceHolder,
                $coursePlaceHolder
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
                ) activity
                INNER JOIN curriculum_node_for_assessment cnfa
                    ON cnfa.assessment_id = activity.assessment_id
                    AND cnfa.curriculum_node_id IN %s
                INNER JOIN assessment_course_unit_specification acus ON acus.assessment_id = cnfa.assessment_id
                INNER JOIN course_unit_specification cus
                        ON cus.id = acus.course_unit_specification_id
                        AND cus.course_specification_id IN %s
                INNER JOIN assessment a ON a.id = activity.assessment_id AND a.deleted_at IS NULL
                GROUP BY type, cnfa.curriculum_node_id, cus.course_specification_id, cus.folder_id
            ",
                self::COURSE_ACTIVITY_TASK,
                self::COURSE_ACTIVITY_QUIZ,
                self::COURSE_ACTIVITY_LTI,
                self::COURSE_ACTIVITY_DUE_WORK,
                self::COURSE_ACTIVITY_PROJECT,
                $nodesPlaceHolder,
                $coursePlaceHolder
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
                     SELECT '%s' type, lp.assessment_id, lp.deleted_at
                     FROM lesson_plan lp
                ) activity
                INNER JOIN curriculum_node_for_assessment cnfa
                    ON cnfa.assessment_id = activity.assessment_id
                    AND cnfa.curriculum_node_id IN %s
                INNER JOIN assessment_course_unit_specification acus ON acus.assessment_id = cnfa.assessment_id
                INNER JOIN course_unit_specification cus
                        ON cus.id = acus.course_unit_specification_id
                        AND cus.course_specification_id IN %s
                WHERE activity.deleted_at IS NULL
                GROUP BY activity.type, cnfa.curriculum_node_id, cus.course_specification_id, cus.folder_id
            ",
                self::COURSE_ACTIVITY_LESSON_PLAN,
                $nodesPlaceHolder,
                $coursePlaceHolder
            );

            //Course level capabilities
            $rubricCapabilityCountQuery = sprintf(
                "
                SELECT
                    '%s' type,
                    cnrc.curriculum_node_id,
                    cs.id courseSpecId,
                    null unitId,
                    COUNT(*) AS count
                FROM rubric_capability rc
                INNER JOIN curriculum_node_for_rubric_capability cnrc
                    ON rc.id = cnrc.rubric_capability_id AND cnrc.curriculum_node_id IN %s
                INNER JOIN course_specification cs ON cs.folder_id = rc.parent_id AND cs.id IN %s
                WHERE rc.deleted_at IS NULL
                GROUP BY cnrc.curriculum_node_id, cs.id, cs.folder_id
                ",
                self::COURSE_RUBRIC_CAPABILITY,
                $nodesPlaceHolder,
                $coursePlaceHolder,
            );

            //Unit level capabilities
            $unitRubricCapabilityCountQuery = sprintf(
                "
                SELECT
                    '%s' type,
                    cnrc.curriculum_node_id,
                    cus.course_specification_id courseSpecId,
                    cus.folder_id unitId,
                    COUNT(*) AS count
                FROM rubric_capability rc
                INNER JOIN curriculum_node_for_rubric_capability cnrc
                ON rc.id = cnrc.rubric_capability_id
                AND cnrc.curriculum_node_id IN %s
                INNER JOIN course_unit_specification cus
                ON cus.folder_id = rc.parent_id AND cus.course_specification_id IN %s
                WHERE rc.deleted_at IS NULL
                GROUP BY cnrc.curriculum_node_id, cus.id, cus.folder_id
                ",
                self::COURSE_UNIT_RUBRIC_CAPABILITY,
                $nodesPlaceHolder,
                $coursePlaceHolder,
            );

            //Course level indicators
            $rubricIndicatorCountQuery = sprintf(
                "
                SELECT
                    '%s' type,
                    cnri.curriculum_node_id,
                    cs.id courseSpecId,
                    null unitId,
                    COUNT(*) AS count
                FROM rubric_indicator ri
                INNER JOIN curriculum_node_for_rubric_indicator cnri
                    ON ri.id = cnri.rubric_indicator_id AND cnri.curriculum_node_id IN %s
                INNER JOIN rubric_capability rc ON rc.id = ri.rubric_capability_id
                INNER JOIN course_specification cs ON cs.folder_id = rc.parent_id AND cs.id IN %s
                WHERE rc.deleted_at IS NULL
                GROUP BY cnri.curriculum_node_id, cs.id, cs.folder_id
                ",
                self::COURSE_RUBRIC_INDICATOR,
                $nodesPlaceHolder,
                $coursePlaceHolder,
            );

            //Unit level indicators
            $unitRubricIndicatorCountQuery = sprintf(
                "
                SELECT
                    '%s' type,
                    cnri.curriculum_node_id,
                    cus.course_specification_id courseSpecId,
                    cus.folder_id unitId,
                    COUNT(*) AS count
                FROM rubric_indicator ri
                INNER JOIN curriculum_node_for_rubric_indicator cnri
                    ON ri.id = cnri.rubric_indicator_id AND cnri.curriculum_node_id IN %s
                INNER JOIN rubric_capability rc ON rc.id = ri.rubric_capability_id
                INNER JOIN course_unit_specification cus
                    ON cus.folder_id = rc.parent_id AND cus.course_specification_id IN %s
                WHERE ri.deleted_at IS NULL
                GROUP BY cnri.curriculum_node_id, cus.course_specification_id, cus.folder_id
                ",
                self::COURSE_UNIT_RUBRIC_INDICATOR,
                $nodesPlaceHolder,
                $coursePlaceHolder,
            );

            $inUseRubricCountQuery = sprintf(
                "
                   SELECT
                       '%s' type,
                       rubric.curriculum_node_id,
                       rubric.course_specification_id,
                       rubric.folder_id,
                       COUNT(*) AS count
                    FROM (
                        SELECT
                            cnrc.curriculum_node_id,
                            cus.course_specification_id,
                            cus.folder_id,
                            v.assessment_id,
                            v.capability_id id,
                            v.capability_name title
                        FROM v_rubric v
                        INNER JOIN curriculum_node_for_rubric_capability cnrc
                            ON cnrc.rubric_capability_id = v.capability_id
                            AND cnrc.curriculum_node_id IN %s
                        INNER JOIN assessment_course_unit_specification acus ON v.assessment_id = acus.assessment_id
                        INNER JOIN course_unit_specification cus
                            ON cus.id = acus.course_unit_specification_id
                            AND cus.course_specification_id IN %s
                        UNION
                        SELECT
                            cnri.curriculum_node_id,
                            cus.course_specification_id,
                            cus.folder_id,
                            v.assessment_id,
                            v.indicator_id id,
                            v.indicator_name title
                        FROM v_rubric v
                        INNER JOIN curriculum_node_for_rubric_indicator cnri
                            ON cnri.rubric_indicator_id = v.indicator_id
                            AND cnri.curriculum_node_id IN %s
                        INNER JOIN assessment_course_unit_specification acus ON v.assessment_id = acus.assessment_id
                        INNER JOIN course_unit_specification cus
                            ON cus.id = acus.course_unit_specification_id
                            AND cus.course_specification_id IN %s
                    ) rubric
                    GROUP BY rubric.curriculum_node_id, rubric.course_specification_id, rubric.folder_id
                ",
                self::COURSE_IN_USE_RUBRIC,
                $nodesPlaceHolder,
                $coursePlaceHolder,
                $nodesPlaceHolder,
                $coursePlaceHolder
            );

            $megaQuery = '( ' .
                implode(
                    ' ) UNION ( ',
                    [
                        $courseCountQuery,
                        $unitCountQuery,
                        $unitActivityCountQuery,
                        $unitLessonPlanCountQuery,
                        $rubricCapabilityCountQuery,
                        $unitRubricCapabilityCountQuery,
                        $rubricIndicatorCountQuery,
                        $unitRubricIndicatorCountQuery,
                        $inUseRubricCountQuery,
                    ]
                ) . ' )';

            $query = $db->executeQuery($megaQuery, $params);

            while ($row = $query->fetch(\PDO::FETCH_ASSOC)) {
                $type = $row['type'];
                if (
                    $type === self::COURSE_COURSE_SPECIFICATION ||
                    $type === self::COURSE_RUBRIC_CAPABILITY ||
                    $type === self::COURSE_RUBRIC_INDICATOR
                ) {
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
        return $this->curriculumMapper->findByColumn('id', $this->nodeId)->count();
    }
}
