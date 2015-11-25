<?php
/**
 * Sync (UPS)
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Config\Modules;


use Alaress\Application\Kernel;

class Sync implements ModuleConfigInterface {

    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     */
    public function configure($kernel) {
        $di = $kernel->getDependencyInjector();

        $ruleset = new \Schoolbox\Sync\Ruleset\Module($di);
        $ruleset->init('/adminv2/sync');

        $rule = new \Schoolbox\Sync\Rule\Module($di);
        $rule->init('/adminv2/sync/rule');

        $condition = new \Schoolbox\Sync\Condition\Module($di);
        $condition->init('/adminv2/sync/condition');
    }
}