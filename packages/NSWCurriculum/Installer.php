<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace NSWCurriculum;

use Schoolbox\Admin\Package\IPackageInstall;
use Schoolbox\Curriculum\Node;
use Alaress\Csv\Reader as CsvReader;
use Schoolbox\User;

/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */
class Installer implements IPackageInstall
{

    /**
     * CSV file from which to load
     */
    private const SOURCE_FILE = 'NSWCurriculum.csv';


    /**
     * Safety mechanism to avoid infinite loops
     */
    private const MAX_CALLS = 100;


    /**
     * Some kind of export includes this word for a column with
     * the datatype null, instead of leaving it empty
     */
    private const NULL_STRING = 'NULL';


    /**
     * @var Node\Mapper
     */
    private Node\Mapper $curriculumNodeMapper;


    /**
     * A list of (csv row id, model id) tuples
     *
     * @var array
     */
    private array $parentLookup = [];


    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->curriculumNodeMapper = Node\Mapper::getInstance();
    }


    /**
     * Install the contents of the source file into the db
     * $user is unused in this installer.
     *
     * @param User $user
     * @return void
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function install(User $user): void
    {
        $path = __DIR__ . '/' . self::SOURCE_FILE;

        if (!(is_file($path) && is_readable($path))) {
            throw new \RuntimeException(sprintf(
                'Package source file not found: %s',
                $path
            ));
        }

        /* Note that csvs can contain html, breaking the column enclosures,
         * causing random exceptions and failures. You can solve it with this
         * cheap test.php
         *
         * new \Alaress_Probe(strip_tags(file_get_contents($path)));
         *
         * Alternatively we can cleanse it, then write to disk for CsvReader to
         * load from the specified $path...
         *
         * ...but it'd be better to feed it functional data instead, from the outset
         */
        $csv = (new CsvReader($path))->setLoadFromFirst(true);

        /* You can no longer presume that the csv nodes are in hierarchical order
         *
         * Some rows reference parent rows that have yet to exist, so you need to add them in place;
         * but to do that, you need to know what those parent nodes should be, which means you
         * need to have read ahead
         */
        $graph = [];

        foreach ($csv as $row) {
            $graph[$row['id']] = $row;
        }

        /* A pretty-bad usecase is when:
         * csv row 1 is the root node
         * csv row 2 is some distant grandchild node (call it "Alice")
         * csv row 3000 is the actual parent node for csv row 2 (call it "Frank")
         *
         * Importing the Alice node will cause importAncestors() to build the entire
         * ancestry, up to and including Frank
         *
         * When Frank is finally encountered, it'll create a new $node unless
         * you skip it by checking the $parentLookup hash
         */
        foreach ($graph as $row) {
            /* Import $row's parent nodes, all the way back to root
             */
            $this->importAncestors($graph, $row);

            /* Import $row itself
            */
            $this->importNode($row);
        }
    }


    /**
     * For the given $row, import its parent node and all the ancestral nodes,
     * right back until the root node is encountered - this is defined by having
     * a null curriculum_node_parent_id
     *
     * @param array $graph
     * @param array $row
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function importAncestors(array $graph, array $row): void
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
                $parent = $graph[$row['curriculum_node_parent_id']];

                $node = $this->curriculumNodeMapper->createModel()
                    ->setParent($parent['curriculum_node_parent_id'] ? $this->parentLookup[$parent['curriculum_node_parent_id']] : null)
                    ->setName($parent['name'])
                    ->setDescription($parent['description'])
                    ->setCode($parent['code'])
                    ->setOrder($parent['order']);

                $this->curriculumNodeMapper->save($node);

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
    private function importNode(array $row): void
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

        $node = $this->curriculumNodeMapper->createModel()
            ->setParent($parentId ? $this->parentLookup[$row['curriculum_node_parent_id']] : null)
            ->setName($row['name'])
            ->setDescription($row['description'])
            ->setCode($row['code'])
            ->setOrder($row['order']);

        $this->curriculumNodeMapper->save($node);

        // Store the inserted ID so we can link children to it
        $this->parentLookup[$row['id']] = $node;
    }
}
