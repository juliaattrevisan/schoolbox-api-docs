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
use Schoolbox\SportsFixtures\Division\Module as DivisionModule;

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
        $messagesModule = new Module($kernel->getDependencyInjector());
        $messagesModule->init('/sportsfixtures');

        $messagesModule = new DivisionModule($kernel->getDependencyInjector());
        $messagesModule->init('/sportsfixtures/division');
    }

} 
