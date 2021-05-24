<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Fixtures\Landing\Module as LandingModule;
use Schoolbox\Fixtures\Import\Module as ImportModule;
use Schoolbox\Fixtures\Sport\Module as SportModule;
use Schoolbox\Fixtures\Season\Module as SeasonModule;
use Schoolbox\Fixtures\Division\Module as DivisionModule;
use Schoolbox\Fixtures\Team\Module as TeamModule;
use Schoolbox\Fixtures\School\Module as SchoolModule;
use Schoolbox\Fixtures\Position\Module as PositionModule;
use Schoolbox\Fixtures\Venue\Module as VenueModule;
use Schoolbox\Fixtures\Match\Module as MatchModule;
use Schoolbox\Fixtures\ItineraryItem\Module as ItineraryItemModule;
use Schoolbox\Fixtures\Component\Homepage\Module as HomepageModule;
use Schoolbox\Fixtures\Component\Dashboard\Module as DashboardModule;
use Schoolbox\Fixtures\Result\Module as ResultModule;
use Schoolbox\Fixtures\Status\Module as StatusModule;

class SportsFixtures implements ModuleConfigInterface
{

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();

        $namespace = 'fixtures';

        $homepageModule = new HomepageModule($di);
        $homepageModule->init("/$namespace");

        if ($di->get('config')->get('fixtures_enabled')) {
            $dashboardModule = new DashboardModule($di);
            $dashboardModule->init("/$namespace");

            $landingModule = new LandingModule($di);
            $landingModule->init("/$namespace");

            $sportModule = new SportModule($di);
            $sportModule->init("/$namespace");

            $schoolModule = new SchoolModule($di);
            $schoolModule->init("/$namespace/school");

            $seasonModule = new SeasonModule($di);
            $seasonModule->init("/$namespace");

            $divisionModule = new DivisionModule($di, $sportModule);
            $divisionModule->init("/$namespace");

            $teamModule = new TeamModule($di, $divisionModule);
            $teamModule->init("/$namespace");

            $importModule = new ImportModule($di);
            $importModule->init("/$namespace");

            $positionModule = new PositionModule($di, $sportModule);
            $positionModule->init("/$namespace");

            $matchModule = new MatchModule($di, $divisionModule);
            $matchModule->init("/$namespace");

            $venueModule = new VenueModule($di);
            $venueModule->init("/$namespace");

            $itineraryItemModule = new ItineraryItemModule($di, $matchModule);
            $itineraryItemModule->init("/$namespace");

            $resultModule = new ResultModule($di, $matchModule);
            $resultModule->init("/$namespace");

            $statusModule = new StatusModule($di, $resultModule);
            $statusModule->init("/$namespace");
        }
    }
}
