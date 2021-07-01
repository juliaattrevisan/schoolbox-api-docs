<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Config\Modules\Custom\tigs;

use Alaress\Application\Kernel;
use Config\ModuleLoader;
use Schoolbox\IConfigLoader;
use Custom\Tigs\Summative;

class SummativeReporting extends ModuleLoader
{

    /**
     * @inheritdoc
     * @param Kernel $kernel
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function configure($kernel): void
    {
        $di = $kernel->getDependencyInjector();

        /** @var IConfigLoader $config */
        $config = $di->get('config');

        if (strtoupper(trim($config->get('site_school_code'))) !== 'TIGS') {
            return;
        }

        $basePath = '/modules/custom/tigs/summative';

        $this->loadModule($kernel, Summative\Admin\Module::class, "{$basePath}/admin");
        $this->loadModule($kernel, Summative\Comment\Module::class, "{$basePath}/comments");
        $this->loadModule($kernel, Summative\SubjectReport\Module::class, "{$basePath}/subject");
        $this->loadModule($kernel, Summative\ParticipationReport\Module::class, "{$basePath}/participation");
    }
}
