<?php

use Schoolbox\Admin\Package\IPackageInstall;
use Schoolbox\Learning\Badge;
use Schoolbox\Curriculum\Node;
use Alaress\Csv\Reader as CsvReader;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Script for installing a curriculum CSV file into the curriculum_node table
 */
class Installer implements IPackageInstall
{

    private const SOURCE_FILE = 'badge.csv';

    public function install(User $user): void
    {
        if (!(is_file(__DIR__ . '/' . self::SOURCE_FILE) && is_readable(__DIR__ . '/' . self::SOURCE_FILE))) {
            throw new RuntimeException('Package source file not found: ' . __DIR__ . '/' . self::SOURCE_FILE);
        }

        $csv = new CsvReader(__DIR__ . '/' . self::SOURCE_FILE);
        $csv->setLoadFromFirst(true);
        $badgeMapper    = Badge\Mapper::getInstance();
        $badgeCatMapper = Badge\Category\Mapper::getInstance();
        $nodeMapper     = Node\Mapper::getInstance();


        foreach ($csv as $row) {
            $badge = $badgeMapper->createModel();
            $badge->setName($row['Title']);
            $badge->setBody('<p>' . $row['Description'] . '</p>');
            $badge->setIcon('/images/badges' . $row['Image']);
            $badge->setCategory($badgeCatMapper->findOneByColumn('name', $row['Category']));

            $curriculumCodes = explode(',', $row['Curriculum']);
            $nodeCollection  = new GenericCollection(Node\Model::class);
            foreach ($curriculumCodes as $code) {
                try {
                    $nodeCollection->add($nodeMapper->findOneByColumn('code', $code));
                } catch (Exception $e) {
                    throw $e;
                }
            }
            $badge->setCurriculumNodes($nodeCollection);

            $badge->setCreatedBy($user);
            $badge->setUpdatedBy($user);


            $badgeMapper->save($badge);
        }
    }
}
