<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Sessions;

use Psr\Log\LoggerInterface;
use Schoolbox\Clipboard\Hierarchy\Lookup\Person;
use Schoolbox\Clipboard\Hierarchy\Registry;
use Schoolbox\Clipboard\IApi;
use Schoolbox\Clipboard\IStrategy;

/**
 * Generate a list of Clipboard Sessions to load as schoolbox Matchups
 */
class Sessions implements IStrategy
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
     * @var IApi
     */
    private IApi $api;


    /**
     * @var Registry
     */
    private Registry $registry;


    /**
     * @var Person
     */
    private \Schoolbox\Clipboard\Hierarchy\Lookup\Person $userLookup;


    /**
     * @var \Schoolbox\Clipboard\Hierarchy\Codec\Division
     */
    private \Schoolbox\Clipboard\Hierarchy\Codec\Division $divisionCodec;


    /**
     * Constructor.
     *
     * @param LoggerInterface $log
     * @param bool            $perform
     * @param IApi            $api
     * @param Registry        $registry
     * @param Person          $userLookup
     */
    public function __construct(
        LoggerInterface $log,
        bool $perform,
        IApi $api,
        Registry $registry,
        Person $userLookup
    ) {
        $this->log = $log;
        $this->perform = $perform;
        $this->api = $api;
        $this->registry = $registry;
        $this->userLookup = $userLookup;
        $this->divisionCodec = new \Schoolbox\Clipboard\Hierarchy\Codec\Division();
    }


    /**
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function import(): void
    {
        $userLookup = $this->userLookup->generate();

        /* Consume data from Clipboard endpoint - each Session comes with info about
         * its own hierarchy so map them to schoolbox concepts as best we can
         */
        $endpoint = 'sessions';

        $this->log->info('');

        $this->log->info(sprintf(
            'Downloading data from endpoint: %s (this might take a while)',
            $endpoint
        ));

        /* I guess have an ultralong season to basically remove it from typical usage
         * This season basically encompasses everything so we only need to spawn it once
         */
        $season = $this->registry->recallSeason();

        $sessions = $this->api->retrieve('GET', $endpoint, [
            'includeTeams' => 'true',
            'includeStaff' => 'true',
            'includeRoundName' => 'true',
            'includeStatuses' => ['confirmed'],
            /* Apparently this should give you "only not cancelled" sessions
             */
            'cancelled' => 'false'
        ]);

        foreach ($sessions['data'] as $session) {
            /* Sport identifiers
             */
            $teamActivityId = (int)$session['activity']['id'];
            $teamActivity = $session['activity']['name'] ?? '';

            foreach ($session['teams'] as $teamData) {
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
                $sport = $this->registry->recallSport($teamActivityId, $teamActivity);

                /* Try to spawn a corresponding Division instance
                 */
                $division = $this->registry->recallDivision($season, $teamActivityId, $sport, $divisionName);

                /* Try to spawn a corresponding Team instance
                 * $teamId is used later to figure out who vs who, when importing Matchups
                 * Teams are indexed by ids for this particular usage, unlike others
                 */
                $team = $this->registry->recallTeam($teamActivityId, $division, $teamId, $teamName);

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
}