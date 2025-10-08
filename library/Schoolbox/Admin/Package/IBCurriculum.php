<?php

namespace Schoolbox\Admin\Package;

class IBCurriculum extends BaseCurriculum
{
    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/example.csv';


    /**
     * Reuse this node if it already exists in the root level
     */
    protected const IB_ROOT_NODE_NAME = 'IB Curriculum';


    /**
     * For the given $row, import its parent node and all the ancestral nodes,
     * right back until the root node is encountered - this is defined by having
     * a null curriculum_node_parent_id
     *
     * Note that in this IB package, we specifically look for a preexisting IB root node,
     * reusing it if it exists; otherwise, creates one
     *
     * @param array $graph
     * @param array $row
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    protected function importAncestors(array $graph, array $row): void
    {
        $next = $row['curriculum_node_parent_id'];

        /* Treat this special word with special care
         */
        if (self::NULL_STRING === $next) {
            $next = null;
        }

        /* Have a max of MAX_CALL ancestors
         * I guess 100 might be enough, but reconfigure it as you need
         */
        for ($i = 0; $next && $i < self::MAX_CALLS; $i++) {
            if (!isset($this->parentLookup[$next])) {
                $uuid = $parent['uuid'] ?? null;

                /* check if uuid is empty string, if set it to null
                 */
                if ($uuid === '') {
                    $uuid = null;
                }

                /* Identify the root node id
                 */
                $parent = $graph[$row['curriculum_node_parent_id']];

                /* If the root node id is 'NULL', a literal string (lol)
                 * then try to look for a node with the IB_ROOT_NODE_NAME.
                 * Use that if it already exists.
                 *
                 * Otherwise, spawn it.
                 */
                if (self::NULL_STRING === trim((string)$row['curriculum_node_parent_id'])) {
                    try {
                        $node = $this->curriculumMapper->findOneByColumn('name', self::IB_ROOT_NODE_NAME);
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        $node = $this->getCurriculumMapper()->createModel()
                            ->setParent($parent['curriculum_node_parent_id'] ? $this->parentLookup[$parent['curriculum_node_parent_id']] : null)
                            ->setName($parent['name'])
                            ->setDescription($parent['description'])
                            ->setCode(trim($parent['code']))
                            ->setOrder($parent['order'])
                            ->setUuid($uuid);

                        $this->getCurriculumMapper()->save($node);
                    }
                } else {
                    $node = $this->getCurriculumMapper()->createModel()
                        ->setParent($parent['curriculum_node_parent_id'] ? $this->parentLookup[$parent['curriculum_node_parent_id']] : null)
                        ->setName($parent['name'])
                        ->setDescription($parent['description'])
                        ->setCode(trim($parent['code']))
                        ->setOrder($parent['order'])
                        ->setUuid($uuid);

                    $this->getCurriculumMapper()->save($node);
                }

                // Store the inserted ID so we can link children to it
                $this->parentLookup[$parent['id']] = $node;

                /* Because NULL string is not the same as a NULL data type
                 * and people are going to forget to clear this in the csv
                 */
                if (self::NULL_STRING === $parent['curriculum_node_parent_id']) {
                    $next = null;
                } else {
                    $next = (int)$parent['curriculum_node_parent_id'];
                }
            }
        }
    }



    /**
     * Import the $row node
     *
     * @param array $row
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    protected function importNode(array $row): void
    {
        /* $row might have been imported by importAncestors()
         *
         * This predominantly affects immediate descendents of root node
         * if they appear in the csv at the very end, but the first few
         * lines of csv use those as parents
         */
        if (isset($this->parentLookup[$row['id']])) {
            return;
        }

        $parentId = $row['curriculum_node_parent_id'];

        /* Treat this special word with special care
         */
        if (self::NULL_STRING === $parentId) {
            $parentId = null;
        }

        $uuid = $row['uuid'] ?? null;

        /* check if uuid is empty string, if set it to null
         */
        if ($uuid === '') {
            $uuid = null;
        }

        /* If the root node id is 'NULL', a literal string (lol)
         * then try to look for a node with the IB_ROOT_NODE_NAME.
         * Use that if it already exists.
         *
         * Otherwise, spawn it.
         */
        if (self::NULL_STRING === trim((string)$row['curriculum_node_parent_id'])) {
            try {
                $node = $this->curriculumMapper->findOneByColumn('name', self::IB_ROOT_NODE_NAME);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $node = $this->getCurriculumMapper()->createModel()
                    ->setParent($parentId ? $this->parentLookup[$row['curriculum_node_parent_id']] : null)
                    ->setName($row['name'])
                    ->setDescription($row['description'])
                    ->setCode(trim($row['code']))
                    ->setOrder($row['order'])
                    ->setUuid($uuid);

                $this->getCurriculumMapper()->save($node);
            }
        } else {
            $node = $this->getCurriculumMapper()->createModel()
                ->setParent($parentId ? $this->parentLookup[$row['curriculum_node_parent_id']] : null)
                ->setName($row['name'])
                ->setDescription($row['description'])
                ->setCode(trim($row['code']))
                ->setOrder($row['order'])
                ->setUuid($uuid);

            $this->getCurriculumMapper()->save($node);
        }

        // Store the inserted ID so we can link children to it
        $this->parentLookup[$row['id']] = $node;
    }
}
