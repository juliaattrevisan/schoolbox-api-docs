<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace AustralianCurriculumV9GeneralCapabilities;

use Schoolbox\Admin\Package;
use Alaress\Csv\Reader as CsvReader;
use Schoolbox\User;

/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */
class Installer extends Package\BaseCurriculum implements Package\IPackageInstall
{
    /**
     * @inheritdoc
     */
    protected string $sourceFile = __DIR__ . '/v9generalcapabilities.csv';

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
        if (!(is_file($this->sourceFile) && is_readable($this->sourceFile))) {
            throw new \RuntimeException(sprintf(
                'Package source file not found: %s',
                $this->sourceFile
            ));
        }

        /* Note that csvs can contain html, breaking the column enclosures,
         * causing random exceptions and failures. You can solve it with this
         * cheap test.php
         *
         * new \Alaress_Probe(strip_tags(file_get_contents($this->sourceFile)));
         *
         * Alternatively we can cleanse it, then write to disk for CsvReader to
         * load from the specified $this->sourceFile...
         *
         * ...but it'd be better to feed it functional data instead, from the outset
         */
        $csv = (new CsvReader($this->sourceFile))->setLoadFromFirst(true);
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
            $node = $this->getCurriculumMapper()->createModel();
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

            $this->getCurriculumMapper()->save($node);

            // Store the inserted ID so we can link children to it
            $parentLookup[$row['id']] = $node;
        }

        // fix for parents that were added later in the csv
        foreach ($missingParents as $orphan) {
            if (isset($parentLookup[$orphan['parentID']])) {
                $orphan['node']->setParent($orphan['parentID'] ? $parentLookup[$orphan['parentID']] : null);
                $this->getCurriculumMapper()->save($orphan['node']);
            }
        }
    }
}
