<?php

/**
 * ApiTest
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Grades\Cohort\Test;

use Schoolbox\Learning\Grades\Cohort;
use PHPUnit\Framework\TestCase;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;
use Schoolbox\YearLevel;

/**
 * Class ValidUseCohortMarksTest
 *
 * @package Schoolbox\Learning\Grades\Cohort\Test
 */
class ValidUseCohortMarksTest extends TestCase
{
    /**
     * Test valid enabling of LMS Cohort Comparison
     *
     * @param bool         $configOption
     * @param Folder\Model $folder
     * @param bool         $expected
     * @throws \PHPUnit\Framework\ExpectationFailedException
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provider_validLMSCohortComparison')]
    public function testValidLMSCohortComparison(bool $configOption, Folder\Model $folder, bool $expected): void
    {
        $actual = (new Cohort\ValidUseCohortMarks())->isSatisfiedBy($configOption, $folder);

        $this->assertEquals($expected, $actual);
    }


    /**
     * Data provider for testValidLMSCohortComparison
     *
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public static function provider_validLMSCohortComparison(): array
    {
        $folderMapper = Folder\Mapper::getInstance();
        $yearLevelMapper = YearLevel\Mapper::getInstance();

        $collection1 = (new GenericCollection(YearLevel\Model::class))
            ->add($yearLevelMapper->createModel()->setLmsCohortComparison(true));

        /* Falls back to default config option
         */
        $collection2 = (new GenericCollection(YearLevel\Model::class))
            ->add($yearLevelMapper->createModel()->setLmsCohortComparison(true))
            ->add($yearLevelMapper->createModel()->setLmsCohortComparison(true))
            ->add($yearLevelMapper->createModel()->setLmsCohortComparison(true))
            ->add($yearLevelMapper->createModel()->setLmsCohortComparison(true));

        /* Falls back to default config option
         */
        $collection3 = (new GenericCollection(YearLevel\Model::class))
            ->add($yearLevelMapper->createModel()->setLmsCohortComparison(true))
            ->add($yearLevelMapper->createModel()->setLmsCohortComparison(false))
            ->add($yearLevelMapper->createModel()->setLmsCohortComparison(true))
            ->add($yearLevelMapper->createModel()->setLmsCohortComparison(false));

        /* Falls back to default config option
         */
        $collection4 = (new GenericCollection(YearLevel\Model::class))
            ->add($yearLevelMapper->createModel()->setLmsCohortComparison(false))
            ->add($yearLevelMapper->createModel()->setLmsCohortComparison(false))
            ->add($yearLevelMapper->createModel()->setLmsCohortComparison(false))
            ->add($yearLevelMapper->createModel()->setLmsCohortComparison(false));

        $collection5 = (new GenericCollection(YearLevel\Model::class))
            ->add($yearLevelMapper->createModel()->setLmsCohortComparison(false));


        return [

            /* One YearLevel instance; use its lmsCohortComparison bool
             */
            'Enabled 1' => [
                true,
                $folderMapper->createModel()->setYearLevels($collection1),
                true
            ],

            /* Multiple YearLevel instances; falls back to global config setting
             */
            'Enabled 2' => [
                true,
                $folderMapper->createModel()->setYearLevels($collection2),
                true
            ],

            /* Multiple YearLevel instances; falls back to global config setting
             */
            'Enabled 3' => [
                true,
                $folderMapper->createModel()->setYearLevels($collection3),
                true
            ],

            /* Multiple YearLevel instances; falls back to global config setting
             */
            'Enabled 4' => [
                true,
                $folderMapper->createModel()->setYearLevels($collection4),
                true
            ],

            /* One YearLevel instance; use its lmsCohortComparison bool
             */
            'Disabled 1' => [
                false,
                $folderMapper->createModel()->setYearLevels($collection5),
                false
            ],

            /* Multiple YearLevel instances; falls back to global config setting
             */
            'Disabled 2' => [
                false,
                $folderMapper->createModel()->setYearLevels($collection2),
                false
            ],

            /* Multiple YearLevel instances; falls back to global config setting
             */
            'Disabled 3' => [
                false,
                $folderMapper->createModel()->setYearLevels($collection3),
                false
            ],

            /* Multiple YearLevel instances; falls back to global config setting
             */
            'Disabled 4' => [
                false,
                $folderMapper->createModel()->setYearLevels($collection4),
                false
            ],

            /* Null collection; falls back to global config setting
             */
            'Default 1' => [
                false,
                $folderMapper->createModel(),
                false
            ],

            /* Null collection; falls back to global config setting
             */
            'Default 2' => [
                true,
                $folderMapper->createModel(),
                true
            ]
        ];
    }
}
