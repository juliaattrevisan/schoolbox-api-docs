<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Config\Modules;


use Alaress\Application\Kernel;
use Schoolbox\SportsFixtures\Module;
use Schoolbox\SportsFixtures\Sport\Module as SportModule;
use Schoolbox\SportsFixtures\Division\Module as DivisionModule;
use Schoolbox\SportsFixtures\Team\Module as TeamModule;
use Schoolbox\SportsFixtures\Position\Module as PositionModule;
use Schoolbox\SportsFixtures\Venue\Module as VenueModule;
use Schoolbox\SportsFixtures\Match\Module as MatchModule;


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
        $namespace = 'sportsfixtures';

        $sportModule = new SportModule($kernel->getDependencyInjector());
        $sportModule->init("/$namespace");

        $divisionModule = new DivisionModule($kernel->getDependencyInjector(), $sportModule);
        $divisionModule->init("/$namespace");

        $teamModule = new TeamModule($kernel->getDependencyInjector(), $divisionModule);
        $teamModule->init("/$namespace");

        $positionModule = new PositionModule($kernel->getDependencyInjector(), $sportModule);
        $positionModule->init("/$namespace");

        $matchModule = new MatchModule($kernel->getDependencyInjector(), $divisionModule);
        $matchModule->init("/$namespace");

        $venueModule = new VenueModule($kernel->getDependencyInjector());
        $venueModule->init("/$namespace");
    }
}
