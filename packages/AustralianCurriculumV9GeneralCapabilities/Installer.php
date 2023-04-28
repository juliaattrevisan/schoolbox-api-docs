<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace AustralianCurriculumV9GeneralCapabilities;

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
     * list of packages this depends on existing in the system
     */
    public const REQUIRED_PACKAGES = [];

    /**
     * CSV file from which to load
     */
    private const SOURCE_FILE = 'v9generalcapabilities.csv';


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
        /**
         * on occasion node parents will be edited or created after the node was created (in the original export...)
         * This will help fix those relationships...
         * {'node' = curricNode, 'parentID' = id of the parent object}
         */
        $missingParents = [];

        /* Special cast for broken csv
         * I had to forcibly insert Victorian Curriculum as node 0
         * Hence the specific check for !== 'NULL'
         */
        foreach ($csv as $row) {
            $node = $curriculumMapper->createModel();
            //check if parent exists? if missing then save these details so we can go back later
            if (isset($parentLookup[$row['curriculum_node_parent_id']])) {
                $node->setParent($row['curriculum_node_parent_id'] ? $parentLookup[$row['curriculum_node_parent_id']] : null);
            } else {
                $node->setParent(null);
                $missingParents[] = ['node' => $node, 'parentID' => $row['curriculum_node_parent_id'] ];
            }
                $node
                ->setName($row['name'])
                ->setDescription($row['description'])
                ->setCode(trim($row['code']))
                ->setOrder($row['order']);

            $curriculumMapper->save($node);

            // Store the inserted ID so we can link children to it
            $parentLookup[$row['id']] = $node;
        }

        // fix for parents that were added later in the csv
        foreach ($missingParents as $orphan) {
            if (isset($parentLookup[$orphan['parentID']])) {
                $orphan['node']->setParent($orphan['parentID'] ? $parentLookup[$orphan['parentID']] : null);
                $curriculumMapper->save($orphan['node']);
            }
        }
    }
}
