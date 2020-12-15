<?php
/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */

use Schoolbox\Admin\Package\IPackageInstall;
use Schoolbox\Curriculum\Node;
use Alaress\Csv\Reader as CsvReader;

class Installer implements IPackageInstall {

    const SOURCE_FILE = 'curriculum.csv';

    public function install() {

        if (!(is_file(SOURCE_FILE) && is_readable(SOURCE_FILE))) {
            throw \Exception('Package source file not found');
        }

        $csv = new CsvReader(SOURCE_FILE);
        $curriculumMapper = Node\Mapper::getInstance();
        $parentLookup = [];

        foreach($csv as $row) {
            $node = new Node\Model();
            $node->setParent($row['curriculum_node_parent_id'] ? $parentLookup[$row['curriculum_node_parent_id']] : null);
            $node->setName($row['name']);
            $node->setDescription($row['description']);
            $node->setCode($row['code']);
            $node->setOrder($row['order']);

            $curriculumMapper->save($node);

            //Store the inserted ID so we can link children to it
            $parentLookup[$row['id']] = $node->getId();
        }
    }
}
