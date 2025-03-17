<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace IBPYPTransDis2025;

use Schoolbox\Admin\Package;

/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */
class Installer extends Package\BaseCurriculum
{
    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/IBPYPTransDis2025.csv';


    /**
     * Reuse this node if it already exists in the root level
     */
    private const IB_ROOT_NODE_NAME = 'IB Curriculum';


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
                $parent = $graph[$row['curriculum_node_parent_id']];

                $node = $this->getCurriculumMapper()->createModel()
                    ->setParent($parent['curriculum_node_parent_id'] ? $this->parentLookup[$parent['curriculum_node_parent_id']] : null)
                    ->setName($parent['name'])
                    ->setDescription($parent['description'])
                    ->setCode(trim($parent['code']))
                    ->setOrder($parent['order'])
                    ->setUuid($uuid);

                $this->getCurriculumMapper()->save($node);

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
}
