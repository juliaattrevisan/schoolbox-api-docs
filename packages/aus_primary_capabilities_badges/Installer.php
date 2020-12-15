<?php
/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */

use Schoolbox\Admin\Package\IPackageInstall;
use Schoolbox\Learning\Badge;
use Schoolbox\Curriculum\Node;
use Alaress\Csv\Reader as CsvReader;
use Schoolbox\GenericCollection;

class Installer implements IPackageInstall {

    private const SOURCE_FILE = 'badge.csv';

    public function install() {

        if (!(is_file(__DIR__ . '/' . self::SOURCE_FILE) && is_readable(__DIR__ . '/' . self::SOURCE_FILE))) {
            throw new \RuntimeException('Package source file not found: '. __DIR__ . '/' . self::SOURCE_FILE);
        }

        $csv = new CsvReader(__DIR__ .'/'. self::SOURCE_FILE);
        $csv->setLoadFromFirst(true);
        $badgeMapper = Badge\Mapper::getInstance();
        $badgeCatMapper = Badge\Category\Mapper::getInstance();
        $nodeMapper = Node\Mapper::getInstance();


        foreach($csv as $row) {
            $badge = new Badge\Model();
            $badge->setName($row['Title']);
            $badge->setBody('<p>'.$row['Description'].'</p>');
            $badge->setIcon($row['Image']);
            $badge->setCategory($badgeCatMapper->findOneByColumn('name', $row['Category']));

            $curriculumCodes = explode(',',$row['Curriculum']);
            $nodeCollection = new GenericCollection(Node\Model::class);
            foreach($curriculumCodes as $code) {
                $nodeCollection->add($nodeMapper->findOneByColumn('code', $code));
            }
            $badge->setCurriculumNodes($nodeCollection);

            $badge->setCreatedBy($_SESSION['user']);
            $badge->setUpdatedBy($_SESSION['user']);


            $badgeMapper->save($badge);
        }
    }
}
