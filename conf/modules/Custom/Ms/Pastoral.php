<?php

/**
 * Configuration for the Meriden-specific pastoral system.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Ms;

use Custom\Ms\Pastoral\Module;
use Config\Modules\ModuleConfigInterface;
use Psr\Container\ContainerInterface;

class Pastoral implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $pastoralModule = new Module($di);
        $pastoralModule->registerModule('/modules/custom/meriden/pastoral');
        $pastoralModule->init('/modules/custom/meriden/pastoral');
    }
}
