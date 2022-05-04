<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Sk;

use Custom\Sk\NCEA\Module as NCEAModule;
use Config\Modules\ModuleConfigInterface;
use Psr\Container\ContainerInterface;

class NCEA implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $module = new NCEAModule($di);
        $module->registerModule('/modules/custom/stk/ncea');
    }
}
