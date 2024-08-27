<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Fixtures\Authorizer\Rule\IsSeasonNotArchived;
use Schoolbox\Fixtures\Sport;
use Schoolbox\Fixtures\Season;
use Schoolbox\Fixtures\Division;
use Schoolbox\Fixtures\Team;
use Alaress_Date_Format;

class ArchivedSeason extends TestCase
{
    /**
     * Ensure Ids are unique so we don't run into cache complications
     *
     * Interestingly, this must be static because PHP_Unit creates many instances of
     * this class, and the ID must be unique across all to avoid mapper cache issues.
     *
     * @var int
     */
    private static $nextId = 1;


    public function testIsArchived()
    {
        $season = self::buildArchivedSeason();
        $this->assertTrue($season->isArchived());
    }


    public function testIsNotArchived()
    {
        $season = self::buildNonArchivedSeason();
        $this->assertFalse($season->isArchived());
    }


    /**
     * @param array $routeParams
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSeasonNotFound')]
    public function testSeasonNotFoundInParams(array $routeParams)
    {
        $this->expectException(\InvalidArgumentException::class);
        $rule = new IsSeasonNotArchived();
        $rule->authorize($routeParams);
    }


    /**
     * @param array $routeParams
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSeasonNotArchived')]
    #[\PHPUnit\Framework\Attributes\DoesNotPerformAssertions]
    public function testSeasonNotArchived(array $routeParams)
    {
        $rule = new IsSeasonNotArchived();
        $rule->authorize($routeParams);
    }


    /**
     * @param array $routeParams
     *
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerSeasonIsArchived')]
    public function testSeasonIsArchived(array $routeParams)
    {
        $this->expectException(\Alaress\Authorizer\Failure\Unauthorized::class);
        $rule = new IsSeasonNotArchived();
        $rule->authorize($routeParams);
    }


    // --------------------- Data Providers ----------------------------


    /**
     * @return array
     */
    public static function providerSeasonNotFound()
    {
        return [
            [[]],
            [
                [
                    'wrongNameSeason' => self::buildArchivedSeason(),
                ],
            ],
            [
                [
                    'anotherWrongNameSeason' => self::buildNonArchivedSeason(),
                ],
            ],
            [
                [
                    'wrongNameSeason'        => self::buildArchivedSeason(),
                    'anotherWrongNameSeason' => self::buildNonArchivedSeason(),
                ],
            ],
            [
                [
                    'team'     => self::buildArchivedSeason(),
                    'division' => self::buildNonArchivedSeason(),
                ],
            ],
            [
                [
                    'season' => self::buildTeamInArchivedSeason(),
                ],
            ],
            [
                [
                    'context' => self::buildTeamInArchivedSeason(),
                ],
            ],
        ];
    }


    /**
     * @return array
     */
    public static function providerSeasonNotArchived()
    {
        return [
            [
                [
                    'season' => self::buildNonArchivedSeason(),
                ],
            ],
            [
                [
                    'entity' => self::buildNonArchivedSeason(),
                ],
            ],
            [
                [
                    'division' => self::buildDivisionInNonArchivedSeason(),
                ],
            ],
            [
                [
                    'entity' => self::buildDivisionInNonArchivedSeason(),
                ],
            ],
            [
                [
                    'context' => self::buildDivisionInNonArchivedSeason(),
                ],
            ],
            [
                [
                    'team' => self::buildTeamInNonArchivedSeason(),
                ],
            ],
            [
                [
                    'entity' => self::buildTeamInNonArchivedSeason(),
                ],
            ],
            [
                [
                    'season'   => self::buildNonArchivedSeason(),
                    'team'     => self::buildTeamInArchivedSeason(),
                    'division' => self::buildDivisionInArchivedSeason(),
                    'context'  => self::buildDivisionInArchivedSeason(),
                ],
            ],
            [
                [
                    'entity'   => self::buildNonArchivedSeason(),
                    'team'     => self::buildTeamInArchivedSeason(),
                    'division' => self::buildDivisionInArchivedSeason(),
                    'context'  => self::buildDivisionInArchivedSeason(),
                ],
            ],
        ];
    }


    public static function providerSeasonIsArchived()
    {
        return [
            [
                [
                    'season' => self::buildArchivedSeason(),
                ],
            ],
            [
                [
                    'entity' => self::buildArchivedSeason(),
                ],
            ],
            [
                [
                    'division' => self::buildDivisionInArchivedSeason(),
                ],
            ],
            [
                [
                    'entity' => self::buildDivisionInArchivedSeason(),
                ],
            ],
            [
                [
                    'team' => self::buildTeamInArchivedSeason(),
                ],
            ],
            [
                [
                    'entity' => self::buildTeamInArchivedSeason(),
                ],
            ],
            [
                [
                    'season'   => self::buildArchivedSeason(),
                    'team'     => self::buildTeamInNonArchivedSeason(),
                    'division' => self::buildDivisionInNonArchivedSeason(),
                ],
            ],
            [
                [
                    'entity'   => self::buildArchivedSeason(),
                    'team'     => self::buildTeamInNonArchivedSeason(),
                    'division' => self::buildDivisionInNonArchivedSeason(),
                ],
            ],
            [
                [
                    'season' => self::buildArchivedSeason(),
                    'entity' => self::buildTeamInNonArchivedSeason(),
                ],
            ],
        ];
    }

    // --------------------- Helpers ----------------------------


    /**
     * @return Team\Model
     */
    private static function buildTeamInNonArchivedSeason()
    {
        $team = self::buildTeamInArchivedSeason();
        $team->setName('Team in non-archived season');
        $team->getDivision()->getSeason()->setArchivedAt(null);
        return $team;
    }


    /**
     * @return Team\Model
     */
    private static function buildTeamInArchivedSeason()
    {
        $mapper = Team\Mapper::getInstance();
        $model  = $mapper->createModel(self::getUniqueId());
        $model
            ->setDivision(self::buildDivisionInArchivedSeason())
            ->setName('Team in archived season')
            ->setUrl('http://www.google.com')
            ->setCreatedAt(self::buildDate('2001-01-01'))
            ->setUpdatedAt(self::buildDate('2001-01-01'))
            ->setDeletedAt(null);
        return $model;
    }


    /**
     * @return Division\Model
     */
    private static function buildDivisionInNonArchivedSeason()
    {
        $division = self::buildDivisionInArchivedSeason();
        $division->setName('Division linked to non-archived season');
        $division->getSeason()->setArchivedAt(null);
        return $division;
    }


    /**
     * @return Division\Model
     */
    private static function buildDivisionInArchivedSeason()
    {
        $sportMapper    = Sport\Mapper::getInstance();
        $divisionMapper = Division\Mapper::getInstance();
        $division       = $divisionMapper->createModel(self::getUniqueId());
        $division
            ->setSport($sportMapper->createModel(self::getUniqueId()))
            ->setSeason(self::buildArchivedSeason())
            ->setName('Division linked to archived season')
            ->setCreatedAt(self::buildDate('2001-01-01'))
            ->setUpdatedAt(self::buildDate('2001-01-01'))
            ->setDeletedAt(null);
        return $division;
    }


    /**
     * @return Season\Model
     */
    private static function buildNonArchivedSeason()
    {
        $season = self::buildArchivedSeason();
        $season->setName('Non-Archived')
               ->setArchivedAt(null);
        return $season;
    }


    /**
     * @return Season\Model
     */
    private static function buildArchivedSeason()
    {
        $mapper = Season\Mapper::getInstance();
        $model  = $mapper->createModel(self::getUniqueId());
        $model
            ->setName('Archived Season')
            ->setStart(self::buildDate('2001-01-01'))
            ->setEnd(self::buildDate('2020-01-01'))
            ->setArchivedAt(self::buildDate('2020-01-01'))
            ->setCreatedAt(self::buildDate('2001-01-01'))
            ->setUpdatedAt(self::buildDate('2001-01-01'))
            ->setDeletedAt(null);
        return $model;
    }


    /**
     * @param string $dateString
     * @param string $format
     * @return Alaress_Date_Format
     */
    private static function buildDate($dateString, $format = Alaress_Date_Format::DATABASE_DATE_FORMAT)
    {
        return new Alaress_Date_Format($dateString, $format);
    }


    /**
     * @return int
     */
    private static function getUniqueId()
    {
        return self::$nextId++;
    }
}
