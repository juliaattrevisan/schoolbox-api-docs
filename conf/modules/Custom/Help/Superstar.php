<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules\Custom\Help;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Custom\Help\Superstar\Module as SuperstarModule;

class Superstar extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $this->loadModule($di, SuperstarModule::class, '/superstar');
    }
}
