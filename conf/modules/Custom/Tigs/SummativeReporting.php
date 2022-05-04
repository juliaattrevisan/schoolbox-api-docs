<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules\Custom\Tigs;

use Config\ModuleLoader;
use Custom\Tigs\Summative;
use Psr\Container\ContainerInterface;

class SummativeReporting extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        $basePath = '/modules/custom/tigs/summative';

        $this->loadModule($di, Summative\Admin\Module::class, "{$basePath}/admin");
        $this->loadModule($di, Summative\Comment\Module::class, "{$basePath}/comments");
        $this->loadModule($di, Summative\SubjectReport\Module::class, "{$basePath}/subject");
        $this->loadModule($di, Summative\ParticipationReport\Module::class, "{$basePath}/participation");
    }
}
