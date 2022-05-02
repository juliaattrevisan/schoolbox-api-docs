<?php

/**
 * help.schoolbox.com.au
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Help;

use Config\ModuleLoader;
use Custom\Help\Nesa\Module as NesaModule;
use Psr\Container\ContainerInterface;

class Accreditation extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, NesaModule::class, '/accreditation');
    }
}
