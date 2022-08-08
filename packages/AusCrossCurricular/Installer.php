<?php

namespace AusCrossCurricular;

use Schoolbox\Admin\Package\IPackageInstall;
use Schoolbox\Curriculum\Node;
use Alaress\Csv\Reader as CsvReader;
use Schoolbox\User;

/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */
class Installer implements IPackageInstall
{

    private const SOURCE_FILE = 'curriculum.csv';

    public function install(User $user): void
    {
        if (!(is_file(__DIR__ . '/' . self::SOURCE_FILE) && is_readable(__DIR__ . '/' . self::SOURCE_FILE))) {
            throw new \RuntimeException('Package source file not found: ' . __DIR__ . '/' . self::SOURCE_FILE);
        }

        $csv = new CsvReader(__DIR__ . '/' . self::SOURCE_FILE);
        $csv->setLoadFromFirst(true);
        $curriculumMapper = Node\Mapper::getInstance();
        $parentLookup     = [];

        foreach ($csv as $row) {
            $node = $curriculumMapper->createModel();
            $node->setParent($row['curriculum_node_parent_id'] ? $parentLookup[$row['curriculum_node_parent_id']] : null);
            $node->setName($row['name']);
            $node->setDescription($row['description']);
            $node->setCode($row['code']);
            $node->setOrder($row['order']);

            $curriculumMapper->save($node);

            //Store the inserted ID so we can link children to it
            $parentLookup[$row['id']] = $node;
        }
    }
}
