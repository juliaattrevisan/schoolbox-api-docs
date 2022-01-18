<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace VictorianCurriculum;

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
    private const SOURCE_FILE = 'VicCurriculum.csv';


    /**
     * Name of a root node in the curriculum_node table
     * to which all csv rows are a subset
     */
    private const PARENT_NODE_NAME = 'Victorian Curriculum';


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
        $curriculumMapper = Node\Mapper::getInstance();
        $parentLookup = [];

        /* Find a curriculum_node called "Victorian Curriculum"
         * If it doesn't exist, create it; this is a parent node
         * for all the ones that follow from the csv
         */
        try {
            $rootParentNode = $curriculumMapper->findOneByColumn('name', self::PARENT_NODE_NAME);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $rootParentNode = $curriculumMapper->createModel()
                ->setName(self::PARENT_NODE_NAME)
                ->setOrder(0);

            $curriculumMapper->save($rootParentNode);
        }

        $parentLookup[0] = $rootParentNode->getId();

        foreach ($csv as $row) {
            try {
                if (0 !== (int)$row['curriculum_node_parent_id']) {
                    $rootParentNodeId = $parentLookup[$row['curriculum_node_parent_id']];
                } else {
                    $rootParentNodeId = 0;
                }

                $node = $curriculumMapper->createModel()
                    ->setParent($rootParentNodeId)
                    ->setName($row['name'])
                    ->setDescription($row['description'])
                    ->setCode($row['code'])
                    ->setOrder($row['order']);

//                $curriculumMapper->save($node);

                print_r(sprintf(
                    "Creating node %s with parent id %s\n",
                    $row['name'],
                    $rootParentNodeId
                ));

                // Store the inserted ID so we can link children to it
                $parentLookup[$row['id']] = $node->getId();
            } catch (\Throwable $e) {
                print_r("\n{$e->getMessage()}");
            }
        }
    }
}
