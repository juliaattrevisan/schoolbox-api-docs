<?php

/**
 * Sync (UPS)
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;

class Sync implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $ruleset = new \Schoolbox\Sync\Ruleset\Module($di);
        $ruleset->init('/adminv2/ups');

        $rule = new \Schoolbox\Sync\Rule\Module($di);
        $rule->init('/adminv2/ups/rule');

        $condition = new \Schoolbox\Sync\Condition\Module($di);
        $condition->init('/adminv2/ups/condition');

        $effect = new \Schoolbox\Sync\Effect\Module($di);
        $effect->init('/adminv2/ups/effect');
    }
}
