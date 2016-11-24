<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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
