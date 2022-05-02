<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\Fixtures\Landing\Module as LandingModule;
use Schoolbox\Fixtures\Import\Module as ImportModule;
use Schoolbox\Fixtures\Sport\Module as SportModule;
use Schoolbox\Fixtures\Season\Module as SeasonModule;
use Schoolbox\Fixtures\Division\Module as DivisionModule;
use Schoolbox\Fixtures\Team\Module as TeamModule;
use Schoolbox\Fixtures\School\Module as SchoolModule;
use Schoolbox\Fixtures\Position\Module as PositionModule;
use Schoolbox\Fixtures\Venue\Module as VenueModule;
use Schoolbox\Fixtures\Matchup\Module as MatchupModule;
use Schoolbox\Fixtures\ItineraryItem\Module as ItineraryItemModule;
use Schoolbox\Fixtures\Component\Homepage\Module as HomepageModule;
use Schoolbox\Fixtures\Component\Dashboard\Module as DashboardModule;
use Schoolbox\Fixtures\Result\Module as ResultModule;
use Schoolbox\Fixtures\Status\Module as StatusModule;

class SportsFixtures implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $namespace = 'fixtures';

        $homepageModule = new HomepageModule($di);
        $homepageModule->registerModule("/$namespace");
        $homepageModule->init("/$namespace");

        if ($di->get('config')->get('fixtures_enabled')) {
            $dashboardModule = new DashboardModule($di);
            $dashboardModule->registerModule("/$namespace");
            $dashboardModule->init("/$namespace");

            $landingModule = new LandingModule($di);
            $landingModule->registerModule("/$namespace");
            $landingModule->init("/$namespace");

            $sportModule = new SportModule($di, null);
            $sportModule->registerModule("/$namespace");
            $sportModule->init("/$namespace");

            $schoolModule = new SchoolModule($di);
            $schoolModule->registerModule("/$namespace/school");
            $schoolModule->init("/$namespace/school");

            $seasonModule = new SeasonModule($di, null);
            $seasonModule->registerModule("/$namespace");
            $seasonModule->init("/$namespace");

            $divisionModule = new DivisionModule($di, $sportModule);
            $divisionModule->registerModule("/$namespace");
            $divisionModule->init("/$namespace");

            $teamModule = new TeamModule($di, $divisionModule);
            $teamModule->registerModule("/$namespace");
            $teamModule->init("/$namespace");

            $importModule = new ImportModule($di);
            $importModule->registerModule("/$namespace");
            $importModule->init("/$namespace");

            $positionModule = new PositionModule($di, $sportModule);
            $positionModule->registerModule("/$namespace");
            $positionModule->init("/$namespace");

            $matchupModule = new MatchupModule($di, $divisionModule);
            $matchupModule->registerModule("/$namespace");
            $matchupModule->init("/$namespace");

            $venueModule = new VenueModule($di, null);
            $venueModule->registerModule("/$namespace");
            $venueModule->init("/$namespace");

            $itineraryItemModule = new ItineraryItemModule($di, $matchupModule);
            $itineraryItemModule->registerModule("/$namespace");
            $itineraryItemModule->init("/$namespace");

            $resultModule = new ResultModule($di, $matchupModule);
            $resultModule->registerModule("/$namespace");
            $resultModule->init("/$namespace");

            $statusModule = new StatusModule($di, null);
            $statusModule->registerModule("/$namespace");
            $statusModule->init("/$namespace");
        }
    }
}
