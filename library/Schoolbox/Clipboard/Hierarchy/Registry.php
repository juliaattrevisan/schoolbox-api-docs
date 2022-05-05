<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy;

use Psr\Log\LoggerInterface;
use Schoolbox\Fixtures\Season\Model;

/**
 * Remember an arbitrary $data array via a string $key
 *
 * This is also used as a caching mechanism to retrieve seasons, sports,
 * divisions and teams without polling the db
 *
 * $lookup = [
 *     sport id => [
 *         instance => \Schoolbox\Fixtures\Sport\Model
 *         positions => [
 *             position id => [
 *                 instance => \Schoolbox\Fixtures\Position\Model
 *             ]
 *         ]
 *         divisions => [
 *             division name => [
 *                 instance => \Schoolbox\Fixtures\Division\Model
 *                 teams => [
 *                     team id => [
 *                         instance => \Schoolbox\Fixtures\Team\Model
 *                     ]
 *                 ]
 *             ]
 *         ]
 *     ]
 * ]
 */
class Registry
{

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * @var bool
     */
    private bool $perform;


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
     * @var \Schoolbox\Fixtures\Position\Mapper
     */
    private \Schoolbox\Fixtures\Position\Mapper $positionMapper;


    /**
     * @var \Schoolbox\Fixtures\Division\Mapper
     */
    private \Schoolbox\Fixtures\Division\Mapper $divisionMapper;


    /**
     * @var \Schoolbox\Fixtures\Team\Mapper
     */
    private \Schoolbox\Fixtures\Team\Mapper $teamMapper;


    /**
     * Recall an instance of sport/division/team from schoolbox,
     * storing it in a local cache so these imports go faster
     *
     * Also spawns an arbitrary Season to encompass everything we import
     *
     * @param LoggerInterface                     $log
     * @param bool                                $perform
     * @param \Schoolbox\Fixtures\Season\Mapper   $seasonMapper
     * @param \Schoolbox\Fixtures\Sport\Mapper    $sportMapper
     * @param \Schoolbox\Fixtures\Position\Mapper $positionMapper
     * @param \Schoolbox\Fixtures\Division\Mapper $divisionMapper
     * @param \Schoolbox\Fixtures\Team\Mapper     $teamMapper
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        \Schoolbox\Fixtures\Season\Mapper $seasonMapper,
        \Schoolbox\Fixtures\Sport\Mapper $sportMapper,
        \Schoolbox\Fixtures\Position\Mapper $positionMapper,
        \Schoolbox\Fixtures\Division\Mapper $divisionMapper,
        \Schoolbox\Fixtures\Team\Mapper $teamMapper
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->seasonMapper = $seasonMapper;
        $this->sportMapper = $sportMapper;
        $this->positionMapper = $positionMapper;
        $this->divisionMapper = $divisionMapper;
        $this->teamMapper = $teamMapper;
    }


    /**
     * Spawn a corresponding season if it doesn't already exist
     *
     * This is a special usage that I've hardcoded here; Clipboard removed their Seasons
     * but we still have ours
     *
     * @return Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function recallSeason(): \Schoolbox\Fixtures\Season\Model
    {
        $seasonName = 'Clipboard Events';

        /* Season starts this year and lasts 2 years to basically encompass
         * all future events sent by Clipboard, including ones that broach summer
         * break and to allow future planning beyond
         */
        $start = \Alaress_Date_Format::now()->startOfYear()->startOfDay();
        $end = \Alaress_Date_Format::now()->startOfYear()->startOfDay()->addYears(2);

        try {
            $season = $this->seasonMapper->findOneByColumn('name', $seasonName);

            $this->log->debug(sprintf(
                'Reusing existing season %s %s to %s',
                $season->getName(),
                $season->getStart()->getDatabase(),
                $season->getEnd()->getDatabase()
            ));
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /* Resurrect it from a previously soft-deleted state
             * if it was previously imported from clipboard,
             * deleted from clipboard when this import ran,
             * but reappeared again before this orphaned data was completely removed
             */
            /** @var \Schoolbox\Fixtures\Season\Model $season */
            $season = $e->getDeletedObject();
            $season->setDeletedAt(null);

            $this->log->debug(sprintf(
                'Resurrecting previously-deleted season %s',
                $season->getName()
            ));
        } catch (\Schoolbox\Mapper\Exception $e) {
            $season = $this->seasonMapper->createModel()
                ->setName($seasonName)
                ->setStart($start)
                ->setEnd($end);

            $this->log->debug(sprintf(
                'Creating new season %s %s-%s',
                $season->getName(),
                $season->getStart()->getDatabase(),
                $season->getEnd()->getDatabase()
            ));
        }

        /* We saw this during the import and thus we should keep it
         */
        if ($this->perform) {
            $this->seasonMapper->save($season);
        }

        return $season;
    }


    /**
     * Find the Sport instance corresponding to $sportName,
     * spawning and remembering it if we didn't encounter it previously
     *
     * @param int    $teamActivityId
     * @param string $sportName
     * @return \Schoolbox\Fixtures\Sport\Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function recallSport(int $teamActivityId, string $sportName): \Schoolbox\Fixtures\Sport\Model
    {
        if (!isset($this->lookup[$teamActivityId])) {
            try {
                /* Resurrect it from a previously soft-deleted state
                 * if it was previously imported from clipboard,
                 * deleted from clipboard when this import ran,
                 * but reappeared again before this orphaned data was completely removed
                 */
                $sport = $this->sportMapper->findOneByColumn('name', $sportName);

                $this->log->debug(sprintf(
                    'Reusing existing sport %s',
                    $sport->getName()
                ));
            } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
                /* Resurrect it from a previously soft-deleted state
                 * if it was previously imported from clipboard,
                 * deleted from clipboard when this import ran,
                 * but reappeared again before this orphaned data was completely removed
                 */
                /** @var \Schoolbox\Fixtures\Sport\Model $sport */
                $sport = $e->getDeletedObject();
                $sport->setDeletedAt(null);

                $this->log->debug(sprintf(
                    'Resurrecting previously-deleted sport %s',
                    $sport->getName()
                ));
            } catch (\Schoolbox\Mapper\Exception $e) {
                $sport = $this->sportMapper->createModel()
                    ->setName($sportName);

                $this->log->debug(sprintf(
                    'Creating new sport %s',
                    $sport->getName()
                ));
            }

            /* Dodge around the postsave callback which respawns positions that we
             * set as 0000-00-00 in Prepare by saving them again. This prevents
             * Cleanup from finding unused Positions and softdeleting them
             */
            $sport->setPositions(null);

            /* We saw this during the import and thus we should keep it
             */
            if ($this->perform) {
                $this->sportMapper->save($sport);
            }

            $this->lookup[$teamActivityId] = [
                'instance' => $sport,
                'divisions' => []
            ];
        } else {
            $sport = $this->lookup[$teamActivityId]['instance'];
        }

        return $sport;
    }


    /**
     * Find the Division instance corresponding to $divisionName,
     * spawning and remembering it if we didn't encounter it previously
     *
     * @param Model                           $season
     * @param int                             $teamActivityId
     * @param \Schoolbox\Fixtures\Sport\Model $sport
     * @param string                          $divisionName
     * @return \Schoolbox\Fixtures\Division\Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function recallDivision(
        \Schoolbox\Fixtures\Season\Model $season,
        int $teamActivityId,
        \Schoolbox\Fixtures\Sport\Model $sport,
        string $divisionName
    ): \Schoolbox\Fixtures\Division\Model {
        if (!isset($this->lookup[$teamActivityId]['divisions'][$divisionName])) {
            try {
                $division = $this->divisionMapper->findOneByColumnList([
                    'name' => $divisionName,
                    'sport_id' => $sport->getId()
                ]);

                $this->log->debug(sprintf(
                    'Reusing existing division %s in sport %s',
                    $division->getName(),
                    $sport->getName()
                ));
            } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
                /* Resurrect it from a previously soft-deleted state
                 * if it was previously imported from clipboard,
                 * deleted from clipboard when this import ran,
                 * but reappeared again before this orphaned data was completely removed
                 */
                /** @var \Schoolbox\Fixtures\Division\Model $division */
                $division = $e->getDeletedObject();
                $division->setDeletedAt(null);

                $this->log->debug(sprintf(
                    'Resurrecting previously-deleted division %s in sport %s',
                    $division->getName(),
                    $sport->getName()
                ));
            } catch (\Schoolbox\Mapper\Exception $e) {
                $division = $this->divisionMapper->createModel()
                    ->setName($divisionName)
                    ->setSeason($season)
                    ->setSport($sport);

                $this->log->debug(sprintf(
                    'Creating new division %s in sport %s',
                    $division->getName(),
                    $sport->getName()
                ));
            }

            /* We saw this during the import and thus we should keep it
             */
            if ($this->perform) {
                $this->divisionMapper->save($division);
            }

            $this->lookup[$teamActivityId]['divisions'][$divisionName] = [
                'instance' => $division,
                'teams' => []
            ];
        } else {
            $division = $this->lookup[$teamActivityId]['divisions'][$divisionName]['instance'];
        }

        return $division;
    }


    /**
     * Find the Team instance corresponding to $teamName,
     * spawning and remembering it if we didn't encounter it previously
     *
     * @param int                                $teamActivityId
     * @param \Schoolbox\Fixtures\Division\Model $division
     * @param string                             $teamName
     * @param int                                $teamId
     * @return \Schoolbox\Fixtures\Team\Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function recallTeam(
        int $teamActivityId,
        \Schoolbox\Fixtures\Division\Model $division,
        int $teamId,
        string $teamName
    ): \Schoolbox\Fixtures\Team\Model {
        if (!isset($this->lookup[$teamActivityId]['divisions'][$division->getName()]['teams'][$teamId])) {
            try {
                $team = $this->teamMapper->findOneByColumnList([
                    'name' => $teamName,
                    'division_id' => $division->getId()
                ]);

                $this->log->debug(sprintf(
                    'Reusing existing team %s in division %s',
                    $team->getName(),
                    $division->getName()
                ));
            } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
                /* Resurrect it from a previously soft-deleted state
                 * if it was previously imported from clipboard,
                 * deleted from clipboard when this import ran,
                 * but reappeared again before this orphaned data was completely removed
                 */
                /** @var \Schoolbox\Fixtures\Team\Model $team */
                $team = $e->getDeletedObject();
                $team->setDeletedAt(null);

                $this->log->debug(sprintf(
                    'Resurrecting previously-deleted team %s in division %s',
                    $team->getName(),
                    $division->getName()
                ));
            } catch (\Schoolbox\Mapper\Exception $e) {
                $team = $this->teamMapper->createModel()
                    ->setName($teamName)
                    ->setDivision($division);

                $this->log->debug(sprintf(
                    'Creating new team %s in division %s',
                    $team->getName(),
                    $division->getName()
                ));
            }

            /* We saw this during the import and thus we should keep it
             */
            if ($this->perform) {
                $this->teamMapper->save($team);
            }

            $this->lookup[$teamActivityId]['divisions'][$division->getName()]['teams'][$teamId]['instance'] = $team;
        } else {
            $team = $this->lookup[$teamActivityId]['divisions'][$division->getName()]['teams'][$teamId]['instance'];
        }

        return $team;
    }


    /**
     * Find the Position instance corresponding to $sport,
     * spawning and remembering it if we didn't encounter it previously
     *
     * @param int                             $teamActivityId
     * @param \Schoolbox\Fixtures\Sport\Model $sport
     * @param int                             $positionId
     * @param string                          $positionName
     * @return \Schoolbox\Fixtures\Position\Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function recallPosition(int $teamActivityId, \Schoolbox\Fixtures\Sport\Model $sport, int $positionId, string $positionName): \Schoolbox\Fixtures\Position\Model
    {
        if (!isset($this->lookup[$teamActivityId]['positions'][$positionId])) {
            try {
                $position = $this->positionMapper->findOneByColumnList([
                    'name' => $positionName,
                    'sport_id' => $sport->getId()
                ]);

                $this->log->debug(sprintf(
                    'Reusing existing position %s in sport %s',
                    $position->getName(),
                    $sport->getName()
                ));
            } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
                /* Resurrect it from a previously soft-deleted state
                 * if it was previously imported from clipboard,
                 * deleted from clipboard when this import ran,
                 * but reappeared again before this orphaned data was completely removed
                 */
                /** @var \Schoolbox\Fixtures\Position\Model $position */
                $position = $e->getDeletedObject();
                $position->setDeletedAt(null);

                $this->log->debug(sprintf(
                    'Resurrecting previously-deleted position %s in sport %s',
                    $position->getName(),
                    $sport->getName()
                ));
            } catch (\Schoolbox\Mapper\Exception $e) {
                $position = $this->positionMapper->createModel()
                    ->setName($positionName)
                    ->setSport($sport);

                $this->log->debug(sprintf(
                    'Creating new position %s in sport %s',
                    $position->getName(),
                    $sport->getName()
                ));
            }

            /* We saw this during the import and thus we should keep it
             */
            if ($this->perform) {
                $this->positionMapper->save($position);
            }

            $this->lookup[$teamActivityId]['positions'][$positionId]['instance'] = $position;
        } else {
            $position = $this->lookup[$teamActivityId]['positions'][$positionId]['instance'];
        }

        return $position;
    }


    /* Getters/Setters
     */

    /**
     * Lets you see what the lookup is like, while you're populating it
     *
     * @return array
     */
    public function getLookup(): array
    {
        return $this->lookup;
    }
}
