<?php

namespace Schoolbox\Curriculum\Reports;

use Alaress\Codec\ICodec;
use Alaress\Database\Predicate\Builder;
use Schoolbox\API\Cursor\ICursor;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node\Mapper as CurriculumMapper;

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

    public function __construct(
        private CurriculumMapper $curriculumMapper,
        private array $courseId,
        private array $nodeId,
        private int $limit,
        private ?ICodec $codec = null,
    ) {
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

//            if (empty($results)) {
//                continue;
//            }

//            echo '<pre>', var_dump(array_column($results, 'curriculum_node_id')) , '</pre>';

//            $existingNodes = $this->curriculumMapper->findByColumn('id', $allLineageNodeIds);

//            echo '<pre>', var_dump($existingNodes) , '</pre>';
//            echo '<pre>', var_dump(Collection::arrayColumn($existingNodes, 'id')) , '</pre>';
//            echo '<pre>', var_dump(array_column($existingNodes->getAll(), 'id')) , '</pre>';
//            die();
//            $gg = $this->curriculumMapper->findFamilyTreeIds([$nodeId], 'curriculum_node_parent_id');
//            $gg = $this->curriculumMapper->findByColumn('id', $gg);
//            $tree = $this->curriculumMapper->getCollectionAsTree($gg);

//            $tree = $this->curriculumMapper->getCollectionAsTree(
//                $this->curriculumMapper->findAll(),
//                'parent',
//                'children',
//            );
//
//            $tree->map(fn($g) => var_dump($g->getID()));
//
//            die();

            $test = $this->curriculumMapper->findById($nodeId);
            $collect = $this->curriculumMapper->loadDescendants($test);

            $collect = $this->curriculumMapper->getModelAsCollection($collect);

//            echo '<pre>', var_dump($collect) , '</pre>';
//            $collect->map(fn($g) => var_dump($g->getID()));

//            echo '<pre>', var_dump() , '</pre>';
//            echo '<pre>', var_dump($tree->map(fn($node) => $node->getId())) , '</pre>';
//            die();
//            foreach ($existingNodes as $exitingNode) {
//                echo '<pre>', var_dump($exitingNode->getId()) , '</pre>';
//                echo '<pre>', var_dump($exitingNode->getParent()) , '</pre>';
////                echo '<pre>', var_dump('') , '</pre>';
//            }
            $nodes[] = $collect;
        }

        $response['nodes'] = $nodes;

        return new Collection($response);
    }

    public function retrieveUsageCount(): array
    {
        $inCourseId = '(' . implode(',', $this->courseId) . ')';

        $db = $this->curriculumMapper->getDatabase();

        $count = [];

        $nodes = [];

        foreach ($this->nodeId as $nodeId) {
            $allLineageNodeIds = array_merge(
                [$nodeId],
                $this->curriculumMapper->findDescendantIds([$nodeId], 'curriculum_node_parent_id')
            );

            $findOptions = [
                'limit' => $this->limit,
            ];

            $nodesPlaceHolder = Builder::getArrayPlaceholders($allLineageNodeIds, 'nodes');
            $params           = Builder::getArrayParameters($allLineageNodeIds, 'nodes');

            $courseCountQuery = sprintf(
                "
                SELECT 'courseSpecification' type, cnfc.curriculum_node_id, cs.id courseSpecId, null unitId, COUNT(*) count
                FROM course_specification cs
                INNER JOIN curriculum_node_for_category cnfc ON cs.folder_id = cnfc.category_id AND cs.id IN %s
                WHERE cnfc.curriculum_node_id IN %s
                GROUP BY cnfc.curriculum_node_id, cs.id
                ",
                $inCourseId,
                $nodesPlaceHolder
            );


//            $courseCountResult = $db->executeQuery($courseCountQuery, $params)->fetchAll();

//            echo '<pre>', var_dump($courseCountResult) , '</pre>';

            $unitCountQuery = sprintf(
                "
                SELECT 'unit' type, cnfc.curriculum_node_id, cus.course_specification_id courseSpecId, cus.folder_id unitId, COUNT(*) count
                FROM course_unit_specification cus
                INNER JOIN curriculum_node_for_category cnfc
                    ON cnfc.category_id = cus.folder_id AND cnfc.curriculum_node_id IN %s
                WHERE cus.course_specification_id IN %s
                GROUP BY cnfc.curriculum_node_id, cus.course_specification_id
            ",
                $nodesPlaceHolder,
                $inCourseId
            );

//            $unitCountResult = $db->executeQuery($unitCountQuery, $params)->fetchAll();

//            echo '<pre>', var_dump($unitCountResult) , '</pre>';

            $unitActivityLessonPlanCountQuery = sprintf(
                "
                SELECT
                    activity.type,
                    cnfa.curriculum_node_id,
                    cus.course_specification_id courseSpecId,
                    cus.folder_id as unitId,
                    COUNT(cnfa.curriculum_node_id) as count
                FROM (
                     SELECT 'task' type, t.assessment_id
                     FROM task t
                     UNION
                     SELECT 'quiz' type, a.assessment_id
                     FROM assess a
                     UNION
                     SELECT 'LTI' type, lti.assessment_id
                     FROM lti_assessment lti
                     UNION
                     SELECT 'dueWork' type, sbox.assessment_id
                     FROM submission_box sbox
                     UNION
                     SELECT 'project' type, p.assessment_id
                     FROM project p
                     UNION
                     SELECT 'lessonPlan' type, lp.assessment_id
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
                $nodesPlaceHolder,
                $inCourseId
            );

//            $unitActivityLessonPlanCountResult = $db->executeQuery($unitActivityLessonPlanCountQuery, $params)->fetchAll();

//            $results = array_merge($courseCountResult, $unitCountResult, $unitActivityLessonPlanCountResult);

            $megaQuery = '( ' . implode(' ) UNION ( ', [$courseCountQuery, $unitCountQuery, $unitActivityLessonPlanCountQuery]) . ' )';

            $query = $db->executeQuery($megaQuery, $params);

            foreach ($query->fetchAll() as $row) {
                $type = $row['type'];
                if ($type === 'courseSpecification') {
                    $count[$type][$row['curriculum_node_id']][$row['courseSpecId']] = $row['count'];
                } else {
                    $count[$type][$row['curriculum_node_id']][$row['courseSpecId']][$row['unitId']] = $row['count'];
                }
            }
        }
        return $count;
    }

    /**
     * @inheritDoc
     */
    public function retrieveResultCount(): ?int
    {
        return count($this->nodeId);
    }
}
