<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Hierarchy;

use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\IApi;
use Schoolbox\Clipboard\IStrategy;

/**
 * Import Clipboard teams into schoolbox
 *
 * Clipboard Category/Subcategory equates to schoolbox Divisions which are
 * created progressively
 */
class Team implements IStrategy
{

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $log;


    /**
     * The "perform" flag from the cli tool
     *
     * @var bool
     */
    private bool $perform;


    /**
     * @var IApi
     */
    private IApi $api;


    /**
     * @var Codec\Division
     */
    private Codec\Division $divisionCodec;


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
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param IApi            $api
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        IApi $api
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->divisionCodec = new Codec\Division();

        $this->seasonMapper = \Schoolbox\Fixtures\Season\Mapper::getInstance();
        $this->sportMapper = \Schoolbox\Fixtures\Sport\Mapper::getInstance();
        $this->divisionMapper = \Schoolbox\Fixtures\Division\Mapper::getInstance();
        $this->teamMapper = \Schoolbox\Fixtures\Team\Mapper::getInstance();
    }


    /**
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {

        /* Set up a local cache for (sport, division, team) tuples
         */
        $registry = new Registry(
            $this->log,
            $this->seasonMapper,
            $this->sportMapper,
            $this->divisionMapper,
            $this->teamMapper
        );

        /* I guess have an ultralong season to basically remove it from typical usage
         * This season basically encompasses everything so we only need to spawn it once
         */
        $season = $registry->recallSeason();

        /* Consume data from Clipboard endpoint - each team comes with info about
         * its own hierarchy so map them to schoolbox concepts as best we can
         */
        $endpoint = 'teams';

        $this->log->info(sprintf(
            'Downloading data from endpoint: %s (this might take a while)',
            $endpoint
        ));

        $teams = $this->api->retrieve('GET', $endpoint, []);

        foreach ($teams['data'] as $team) {
            $teamActivity = $team['activity']['name'] ?? '';
            $teamCategory = $team['category']['name'] ?? '';
            $teamSubcategory = $team['subcategory']['name'] ?? '';
            $teamName = $team['name'];

            /* Try to manufacture a division title
             */
            $divisionName = $this->divisionCodec->encode($teamCategory, $teamSubcategory, $teamActivity);

            $this->log->debug(sprintf(
                'Encoded category/subcategory/activity (%s, %s, %s) into schoolbox division name: %s',
                $teamCategory,
                $teamSubcategory,
                $teamActivity,
                $divisionName
            ));

            /* Try to spawn a corresponding Sport instance
             */
            $sport = $registry->recallSport($teamActivity);

            /* Try to spawn a corresponding Division instance
             */
            $division = $registry->recallDivision($season, $sport, $divisionName);

            /* Try to spawn a corresponding Team instance
             */
            $team = $registry->recallTeam($sport, $division, $teamName);

            $this->log->info(sprintf(
                'Sport: %s (id %s), Division: %s (id %s), Team: %s (id %s)',
                $sport->getName(),
                $sport->getId(),
                $division->getName(),
                $division->getId(),
                $team->getName(),
                $team->getId()
            ));
        }
    }
}
