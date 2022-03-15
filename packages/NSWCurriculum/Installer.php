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

        foreach ($graph as $id => $row) {
            /* Check if $row[curriculum_node_parent_id] exists in $parentLookup;
             *
             * If it does - reuse it
             * Otherwise, spawn a new node and store it in the lookup, then use that
             */


            /* Then proceed through the rest of the $graph stack
             */
        }
        foreach ($csv as $row) {
            $node = $curriculumMapper->createModel()
                ->setParent($row['curriculum_node_parent_id'] ? $parentLookup[$row['curriculum_node_parent_id']] : null)
                ->setName($row['name'])
                ->setDescription($row['description'])
                ->setCode($row['code'])
                ->setOrder($row['order']);

            $curriculumMapper->save($node);

            // Store the inserted ID so we can link children to it
            $parentLookup[$row['id']] = $node->getId();
        }
    }
}
