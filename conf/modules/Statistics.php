<?php

/**
 * Configuration for the statistics module.
 *
 * @author    Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Psr\Container\ContainerInterface;

class Statistics implements ModuleConfigInterface
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $statisticsModule = new \Schoolbox\Statistics\Module($di);
        $statisticsModule->registerModule('/statistics');
    }
}
