<?php

namespace AusPersonalDevelopmentBadge;

use Schoolbox\Admin\Package\BadgeCategoryLocator;
use Schoolbox\Admin\Package\IPackageInstall;
use Schoolbox\YearLevel;
use Schoolbox\Learning\Badge;
use Schoolbox\Curriculum\Node;
use Alaress\Csv\Reader as CsvReader;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 * Script for installing a badge CSV file into the badge table
 */
class Installer implements IPackageInstall
{

    private const SOURCE_FILE = 'badge.csv';

    public function install(User $user): void
    {
        if (!(is_file(__DIR__ . '/' . self::SOURCE_FILE) && is_readable(__DIR__ . '/' . self::SOURCE_FILE))) {
            throw new \RuntimeException('Package source file not found: ' . __DIR__ . '/' . self::SOURCE_FILE);
        }

        $csv = new CsvReader(__DIR__ . '/' . self::SOURCE_FILE);
        $csv->setLoadFromFirst(true);
        $badgeMapper          = Badge\Mapper::getInstance();
        $badgeCategoryLocator = new BadgeCategoryLocator(Badge\Category\Mapper::getInstance());
        $nodeMapper           = Node\Mapper::getInstance();


        foreach ($csv as $row) {
            $badge = $badgeMapper->createModel();
            $badge->setName($row['Title']);
            $badge->setBody('<p>' . $row['Description'] . '</p>');
            $badge->setIcon('/images/badges' . $row['Image']);
            $badge->setCategory($badgeCategoryLocator->locate($row['Category'], $user));
            $badge->setAchievementExpiry(Badge\Model::EXPIRY_NEVER);
            $badge->setGoalExpiry(Badge\Model::EXPIRY_NEVER);
            $badge->setAllowAsGoal((bool)$row['Goal']);

            if ($row['Year']) {
                $yearMapper = YearLevel\Mapper::getInstance();
                $yearList = $yearMapper->createCollection();

                foreach (explode(',', $row['Year']) as $year) {
                    try {
                        $yearObject = $yearMapper->findOneByColumn(YearLevel\Mapper::COL_NAME, $year);
                        $yearList->add($yearObject);
                    } catch (\Schoolbox\Mapper\Exception $e) {
                        //retry but this time with the word 'Year ' in front.
                        try {
                            $yearObject = $yearMapper->findOneByColumn(YearLevel\Mapper::COL_NAME, 'Year ' . $year);
                            $yearList->add($yearObject);
                        } catch (\Schoolbox\Mapper\Exception $e) {
                            //noop
                        }
                    }
                }
                $badge->setYearLevels($yearList);
            }

            $curriculumCodes = explode(',', $row['Curriculum']);
            $nodeCollection  = new GenericCollection(Node\Model::class);
            foreach ($curriculumCodes as $code) {
                if ($code === '') {
                    continue;
                }
                try {
                    $nodeCollection->add($nodeMapper->findOneByColumn('code', $code));
                } catch (\Schoolbox\Mapper\Exception $e) {
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
