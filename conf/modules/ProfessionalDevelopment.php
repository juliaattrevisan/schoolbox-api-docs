<?php

/**
 * Professional Development
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;
use Schoolbox\ProfessionalDevelopment\Module;

class ProfessionalDevelopment implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $namespace = 'professionaldevelopment';

        $module = new Module($di);
        $module->init("/$namespace");
    }
}
