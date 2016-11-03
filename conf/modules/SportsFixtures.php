<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Config\Modules;


use Alaress\Application\Kernel;
use Schoolbox\SportsFixtures\Landing\Module as LandingModule;
use Schoolbox\SportsFixtures\Import\Module as ImportModule;
use Schoolbox\SportsFixtures\Sport\Module as SportModule;
use Schoolbox\SportsFixtures\Season\Module as SeasonModule;
use Schoolbox\SportsFixtures\Division\Module as DivisionModule;
use Schoolbox\SportsFixtures\Team\Module as TeamModule;
use Schoolbox\SportsFixtures\School\Module as SchoolModule;
use Schoolbox\SportsFixtures\Position\Module as PositionModule;
use Schoolbox\SportsFixtures\Venue\Module as VenueModule;
use Schoolbox\SportsFixtures\Match\Module as MatchModule;
use Schoolbox\SportsFixtures\ItineraryItem\Module as ItineraryItemModule;
use Schoolbox\SportsFixtures\Component\Homepage\Module as HomepageModule;
use Schoolbox\SportsFixtures\Component\Dashboard\Module as DashboardModule;
use Schoolbox\SportsFixtures\Result\Module as ResultModule;
use Schoolbox\SportsFixtures\Status\Module as StatusModule;

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
        $namespace = 'fixtures';

        $homepageModule = new HomepageModule($kernel->getDependencyInjector());
        $homepageModule->init("/$namespace");

        $dashboardModule = new DashboardModule($kernel->getDependencyInjector());
        $dashboardModule->init("/$namespace");        

        $landingModule = new LandingModule($kernel->getDependencyInjector());
        $landingModule->init("/$namespace");

        $sportModule = new SportModule($kernel->getDependencyInjector());
        $sportModule->init("/$namespace");

        $schoolModule = new SchoolModule($kernel->getDependencyInjector());
        $schoolModule->init("/$namespace/school");

        $seasonModule = new SeasonModule($kernel->getDependencyInjector());
        $seasonModule->init("/$namespace");

        $divisionModule = new DivisionModule($kernel->getDependencyInjector(), $sportModule);
        $divisionModule->init("/$namespace");

        $teamModule = new TeamModule($kernel->getDependencyInjector(), $divisionModule);
        $teamModule->init("/$namespace");

        $importModule = new ImportModule($kernel->getDependencyInjector());
        $importModule->init("/$namespace");

        $positionModule = new PositionModule($kernel->getDependencyInjector(), $sportModule);
        $positionModule->init("/$namespace");

        $matchModule = new MatchModule($kernel->getDependencyInjector(), $divisionModule);
        $matchModule->init("/$namespace");

        $venueModule = new VenueModule($kernel->getDependencyInjector());
        $venueModule->init("/$namespace");

        $itineraryItemModule = new ItineraryItemModule($kernel->getDependencyInjector(), $matchModule);
        $itineraryItemModule->init("/$namespace");

        $resultModule = new ResultModule($kernel->getDependencyInjector(), $matchModule);
        $resultModule->init("/$namespace");

        $statusModule = new StatusModule($kernel->getDependencyInjector(), $resultModule);
        $statusModule->init("/$namespace");
    }
}
