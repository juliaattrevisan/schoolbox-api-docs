<?php

namespace AusGeneralCapabilitiesBadge;

use Schoolbox\Admin\Package\BadgeCategoryLocator;
use Schoolbox\Admin\Package\IPackageInstall;
use Schoolbox\YearLevel;
use Schoolbox\Learning\Badge;
use Schoolbox\Curriculum\Node;
use Alaress\Csv\Reader as CsvReader;
use Schoolbox\User;

/**
 * Script for installing a badge CSV file into the badge table
 */
class Installer implements IPackageInstall
{

    /**
     * The hardcoded source file for this importer
     */
    private const SOURCE_FILE = 'badge.csv';


    /**
     * Install rows from badge.csv under the presumption that an instance's Year Levels
     * are configured in a specific way as described by the csv's Year column.
     *
     * It must match exactly as it is or exactly when the "Year " prefix is added
     * to the column value.
     *
     * @throws \UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \PDOException
     */
    public function install(User $user): void
    {
        $sourceFile = __DIR__ . '/' . self::SOURCE_FILE;

        if (!(is_file($sourceFile) && is_readable($sourceFile))) {
            throw new \RuntimeException('Package source file not found: ' . $sourceFile);
        }

        $badgeMapper          = Badge\Mapper::getInstance();
        $badgeCategoryLocator = new BadgeCategoryLocator(Badge\Category\Mapper::getInstance());
        $nodeMapper           = Node\Mapper::getInstance();
        $yearMapper           = YearLevel\Mapper::getInstance();

        $csv = (new CsvReader($sourceFile))->setLoadFromFirst(true);

        foreach ($csv as $row) {
            /* Decide which year levels to associate with this badge
             */
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

            /* Decide which curriculum codes to associate with this badge
             */
            $curriculumCodes = explode(',', $row['Curriculum']);
            $nodeCollection  = $nodeMapper->createCollection();

            foreach ($curriculumCodes as $code) {
                if ($code === '') {
                    continue;
                }

                $nodeCollection->add($nodeMapper->findOneByColumn('code', $code));
            }

            /* Assign values to the new badge instance
             */
            $badge = $badgeMapper->createModel()
                ->setName($row['Title'])
                ->setBody('<p>' . $row['Description'] . '</p>')
                ->setIcon('/images/badges' . $row['Image'])
                ->setCategory($badgeCategoryLocator->locate($row['Category'], $user))
                ->setAllowAsGoal((bool)$row['Goal'])
                ->setYearLevels($yearList)
                ->setCurriculumNodes($nodeCollection)
                ->setCreatedBy($user)
                ->setUpdatedBy($user);

            $badgeMapper->save($badge);
        }
    }
}
