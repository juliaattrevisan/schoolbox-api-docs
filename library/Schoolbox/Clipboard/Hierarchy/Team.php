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
     * @param LoggerInterface                     $log
     * @param IApi                                $api
     * @param \Schoolbox\Fixtures\Season\Mapper   $seasonMapper
     * @param \Schoolbox\Fixtures\Sport\Mapper    $sportMapper
     * @param \Schoolbox\Fixtures\Position\Mapper $positionMapper
     * @param \Schoolbox\Fixtures\Division\Mapper $divisionMapper
     * @param \Schoolbox\Fixtures\Team\Mapper     $teamMapper
     */
    public function __construct(
        LoggerInterface $log,
        IApi $api,
        \Schoolbox\Fixtures\Season\Mapper $seasonMapper,
        \Schoolbox\Fixtures\Sport\Mapper $sportMapper,
        \Schoolbox\Fixtures\Position\Mapper $positionMapper,
        \Schoolbox\Fixtures\Division\Mapper $divisionMapper,
        \Schoolbox\Fixtures\Team\Mapper $teamMapper
    ) {
        $this->log = $log;
        $this->api = $api;
        $this->divisionCodec = new Codec\Division();
        $this->seasonMapper = $seasonMapper;
        $this->sportMapper = $sportMapper;
        $this->positionMapper = $positionMapper;
        $this->divisionMapper = $divisionMapper;
        $this->teamMapper = $teamMapper;
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
            $this->positionMapper,
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

        $teams = $this->api->retrieve('GET', $endpoint, [
            'includePositions' => 'true'
        ]);

        foreach ($teams['data'] as $teamData) {
            /* Sport identifiers
             */
            $teamActivityId = (int)$teamData['activity']['id'];
            $teamActivity = $teamData['activity']['name'] ?? '';

            /* Division identifiers
             */
            $teamCategory = $teamData['category']['name'] ?? '';
            $teamSubcategory = $teamData['subcategory']['name'] ?? '';

            /* Team identifiers
             */
            $teamId = (int)$teamData['id'];
            $teamName = $teamData['name'];

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
            $sport = $registry->recallSport($teamActivityId, $teamActivity);

            /* Try to spawn a corresponding Division instance
             */
            $division = $registry->recallDivision($season, $teamActivityId, $sport, $divisionName);

            /* Try to spawn a corresponding Team instance
             * $teamId is used later to figure out who vs who, when importing Matchups
             * Teams are indexed by ids for this particular usage, unlike others
             */
            $team = $registry->recallTeam($teamActivityId, $division, $teamId, $teamName);

            $this->log->info(sprintf(
                'Sport: %s (id %s), Division: %s (id %s), Team: %s (id %s)',
                $sport->getName(),
                $sport->getId(),
                $division->getName(),
                $division->getId(),
                $team->getName(),
                $team->getId()
            ));

            /* Each team has a set of positions, but we attribute Positions to
             * the sport in schoolbox. Inspect each team and from its list of
             * Positions, accumulate them into the corresponding Sport
             */
            foreach ($teamData['positions'] as $positionData) {
                $position = $registry->recallPosition($teamActivityId, $sport, (int)$positionData['id'], $positionData['name']);
            }
        }
    }
}
