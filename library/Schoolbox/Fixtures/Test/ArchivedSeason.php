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
        $season = $this->buildArchivedSeason();
        $this->assertTrue($season->isArchived());
    }


    public function testIsNotArchived()
    {
        $season = $this->buildNonArchivedSeason();
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
    public function providerSeasonNotFound()
    {
        return [
            [[]],
            [
                [
                    'wrongNameSeason' => $this->buildArchivedSeason(),
                ],
            ],
            [
                [
                    'anotherWrongNameSeason' => $this->buildNonArchivedSeason(),
                ],
            ],
            [
                [
                    'wrongNameSeason'        => $this->buildArchivedSeason(),
                    'anotherWrongNameSeason' => $this->buildNonArchivedSeason(),
                ],
            ],
            [
                [
                    'team'     => $this->buildArchivedSeason(),
                    'division' => $this->buildNonArchivedSeason(),
                ],
            ],
            [
                [
                    'season' => $this->buildTeamInArchivedSeason(),
                ],
            ],
            [
                [
                    'context' => $this->buildTeamInArchivedSeason(),
                ],
            ],
        ];
    }


    /**
     * @return array
     */
    public function providerSeasonNotArchived()
    {
        return [
            [
                [
                    'season' => $this->buildNonArchivedSeason(),
                ],
            ],
            [
                [
                    'entity' => $this->buildNonArchivedSeason(),
                ],
            ],
            [
                [
                    'division' => $this->buildDivisionInNonArchivedSeason(),
                ],
            ],
            [
                [
                    'entity' => $this->buildDivisionInNonArchivedSeason(),
                ],
            ],
            [
                [
                    'context' => $this->buildDivisionInNonArchivedSeason(),
                ],
            ],
            [
                [
                    'team' => $this->buildTeamInNonArchivedSeason(),
                ],
            ],
            [
                [
                    'entity' => $this->buildTeamInNonArchivedSeason(),
                ],
            ],
            [
                [
                    'season'   => $this->buildNonArchivedSeason(),
                    'team'     => $this->buildTeamInArchivedSeason(),
                    'division' => $this->buildDivisionInArchivedSeason(),
                    'context'  => $this->buildDivisionInArchivedSeason(),
                ],
            ],
            [
                [
                    'entity'   => $this->buildNonArchivedSeason(),
                    'team'     => $this->buildTeamInArchivedSeason(),
                    'division' => $this->buildDivisionInArchivedSeason(),
                    'context'  => $this->buildDivisionInArchivedSeason(),
                ],
            ],
        ];
    }


    public function providerSeasonIsArchived()
    {
        return [
            [
                [
                    'season' => $this->buildArchivedSeason(),
                ],
            ],
            [
                [
                    'entity' => $this->buildArchivedSeason(),
                ],
            ],
            [
                [
                    'division' => $this->buildDivisionInArchivedSeason(),
                ],
            ],
            [
                [
                    'entity' => $this->buildDivisionInArchivedSeason(),
                ],
            ],
            [
                [
                    'team' => $this->buildTeamInArchivedSeason(),
                ],
            ],
            [
                [
                    'entity' => $this->buildTeamInArchivedSeason(),
                ],
            ],
            [
                [
                    'season'   => $this->buildArchivedSeason(),
                    'team'     => $this->buildTeamInNonArchivedSeason(),
                    'division' => $this->buildDivisionInNonArchivedSeason(),
                ],
            ],
            [
                [
                    'entity'   => $this->buildArchivedSeason(),
                    'team'     => $this->buildTeamInNonArchivedSeason(),
                    'division' => $this->buildDivisionInNonArchivedSeason(),
                ],
            ],
            [
                [
                    'season' => $this->buildArchivedSeason(),
                    'entity' => $this->buildTeamInNonArchivedSeason(),
                ],
            ],
        ];
    }

    // --------------------- Helpers ----------------------------


    /**
     * @return Team\Model
     */
    private function buildTeamInNonArchivedSeason()
    {
        $team = $this->buildTeamInArchivedSeason();
        $team->setName('Team in non-archived season');
        $team->getDivision()->getSeason()->setArchivedAt(null);
        return $team;
    }


    /**
     * @return Team\Model
     */
    private function buildTeamInArchivedSeason()
    {
        $mapper = Team\Mapper::getInstance();
        $model  = $mapper->createModel($this->getUniqueId());
        $model
            ->setDivision($this->buildDivisionInArchivedSeason())
            ->setName('Team in archived season')
            ->setUrl('http://www.google.com')
            ->setCreatedAt($this->buildDate('2001-01-01'))
            ->setUpdatedAt($this->buildDate('2001-01-01'))
            ->setDeletedAt(null);
        return $model;
    }


    /**
     * @return Division\Model
     */
    private function buildDivisionInNonArchivedSeason()
    {
        $division = $this->buildDivisionInArchivedSeason();
        $division->setName('Division linked to non-archived season');
        $division->getSeason()->setArchivedAt(null);
        return $division;
    }


    /**
     * @return Division\Model
     */
    private function buildDivisionInArchivedSeason()
    {
        $sportMapper    = Sport\Mapper::getInstance();
        $divisionMapper = Division\Mapper::getInstance();
        $division       = $divisionMapper->createModel($this->getUniqueId());
        $division
            ->setSport($sportMapper->createModel($this->getUniqueId()))
            ->setSeason($this->buildArchivedSeason())
            ->setName('Division linked to archived season')
            ->setCreatedAt($this->buildDate('2001-01-01'))
            ->setUpdatedAt($this->buildDate('2001-01-01'))
            ->setDeletedAt(null);
        return $division;
    }


    /**
     * @return Season\Model
     */
    private function buildNonArchivedSeason()
    {
        $season = $this->buildArchivedSeason();
        $season->setName('Non-Archived')
               ->setArchivedAt(null);
        return $season;
    }


    /**
     * @return Season\Model
     */
    private function buildArchivedSeason()
    {
        $mapper = Season\Mapper::getInstance();
        $model  = $mapper->createModel($this->getUniqueId());
        $model
            ->setName('Archived Season')
            ->setStart($this->buildDate('2001-01-01'))
            ->setEnd($this->buildDate('2020-01-01'))
            ->setArchivedAt($this->buildDate('2020-01-01'))
            ->setCreatedAt($this->buildDate('2001-01-01'))
            ->setUpdatedAt($this->buildDate('2001-01-01'))
            ->setDeletedAt(null);
        return $model;
    }


    /**
     * @param string $dateString
     * @param string $format
     * @return Alaress_Date_Format
     */
    private function buildDate($dateString, $format = Alaress_Date_Format::DATABASE_DATE_FORMAT)
    {
        return new Alaress_Date_Format($dateString, $format);
    }


    /**
     * @return int
     */
    private function getUniqueId()
    {
        return self::$nextId++;
    }
}
