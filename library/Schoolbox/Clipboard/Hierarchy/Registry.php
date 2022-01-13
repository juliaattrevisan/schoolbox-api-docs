<?php
/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy;

use Schoolbox\Fixtures\Season\Model;


/**
 * Remember an arbitrary $data array via a string $key
 *
 * This is also used as a caching mechanism to retrieve sports,
 * divisions and teams without polling the db
 */
class Registry
{

    /**
     * Set up a local cache for (sport, division, team) tuples
     *
     * @var array
     */
    private array $lookup = [];


    /**
     * @var \Schoolbox\Fixtures\Season\Mapper
     */
    private \Schoolbox\Fixtures\Season\Mapper $seasonMapper;


    /**
     * @var \Schoolbox\Fixtures\Sport\Mapper
     */
    private \Schoolbox\Fixtures\Sport\Mapper $sportMapper;


    /**
     * @var \Schoolbox\Fixtures\Division\Mapper
     */
    private \Schoolbox\Fixtures\Division\Mapper $divisionMapper;


    /**
     * @var \Schoolbox\Fixtures\Team\Mapper
     */
    private \Schoolbox\Fixtures\Team\Mapper $teamMapper;


    /**
     * @param \Schoolbox\Fixtures\Season\Mapper   $seasonMapper
     * @param \Schoolbox\Fixtures\Sport\Mapper    $sportMapper
     * @param \Schoolbox\Fixtures\Division\Mapper $divisionMapper
     * @param \Schoolbox\Fixtures\Team\Mapper     $teamMapper
     */
    public function __construct(
        \Schoolbox\Fixtures\Season\Mapper $seasonMapper,
        \Schoolbox\Fixtures\Sport\Mapper $sportMapper,
        \Schoolbox\Fixtures\Division\Mapper $divisionMapper,
        \Schoolbox\Fixtures\Team\Mapper $teamMapper
    ) {
        $this->seasonMapper = $seasonMapper;
        $this->sportMapper = $sportMapper;
        $this->divisionMapper = $divisionMapper;
        $this->teamMapper = $teamMapper;
    }


    /**
     * Find the Sport instance corresponding to $sportName,
     * spawning and remembering it if we didn't encounter it previously
     *
     * @param string $sportName
     * @return \Schoolbox\Fixtures\Sport\Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function recallSport(string $sportName): \Schoolbox\Fixtures\Sport\Model
    {
        if (!isset($this->lookup[$sportName])) {
            try {
                $sport = $this->sportMapper->findOneByColumn('name', $sportName);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $sport = $this->sportMapper->createModel()
                    ->setName($sportName);

                $this->sportMapper->save($sport);
            }

            $this->lookup[$sportName] = [
                'instance' => $sport,
                'divisions' => []
            ];
        } else {
            $sport = $this->lookup[$sportName]['instance'];
        }

        return $sport;
    }


    /**
     * Find the Division instance corresponding to $divisionName,
     * spawning and remembering it if we didn't encounter it previously
     *
     * @param Model                           $season
     * @param \Schoolbox\Fixtures\Sport\Model $sport
     * @param string                          $divisionName
     * @return \Schoolbox\Fixtures\Division\Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function recallDivision(
        \Schoolbox\Fixtures\Season\Model $season,
        \Schoolbox\Fixtures\Sport\Model $sport,
        string $divisionName
    ): \Schoolbox\Fixtures\Division\Model {
        if (!isset($this->lookup[$sport->getName()]['divisions'][$divisionName])) {
            try {
                $division = $this->divisionMapper->findOneByColumnList([
                    'name' => $divisionName,
                    'sport_id' => $sport->getId()
                ]);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $division = $this->divisionMapper->createModel()
                    ->setName($divisionName)
                    ->setSeason($season)
                    ->setSport($sport);

                $this->divisionMapper->save($division);
            }

            $this->lookup[$sport->getName()]['divisions'][$divisionName] = [
                'instance' => $division,
                'teams' => []
            ];
        } else {
            $division = $this->lookup[$sport->getName()]['divisions'][$divisionName]['instance'];
        }

        return $division;
    }


    /**
     * Find the Team instance corresponding to $teamName,
     * spawning and remembering it if we didn't encounter it previously
     *
     * @param \Schoolbox\Fixtures\Sport\Model    $sport
     * @param \Schoolbox\Fixtures\Division\Model $division
     * @param string                             $teamName
     * @return \Schoolbox\Fixtures\Team\Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function recallTeam(
        \Schoolbox\Fixtures\Sport\Model $sport,
        \Schoolbox\Fixtures\Division\Model $division,
        string $teamName
    ): \Schoolbox\Fixtures\Team\Model {
        if (!isset($this->lookup[$sport->getName()]['divisions'][$division->getName()]['teams'][$teamName])) {
            try {
                $team = $this->teamMapper->findOneByColumnList([
                    'name' => $teamName,
                    'division_id' => $division->getId()
                ]);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $team = $this->teamMapper->createModel()
                    ->setName($teamName)
                    ->setDivision($division);

                $this->teamMapper->save($team);
            }

            $this->lookup[$sport->getName()]['divisions'][$division->getName()]['teams'][$teamName]['instance'] = $team;
        } else {
            $team = $this->lookup[$sport->getName()]['divisions'][$division->getName()]['teams'][$teamName]['instance'];
        }

        return $team;
    }
}
