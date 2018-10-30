<?php
/**
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Config\Modules\Custom\Tigs;

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

        if ($config->get('site_location') !== 'tigs') {
            return;
        }

        $basePath = '/modules/custom/tigs/summative';

        $this->loadModule($kernel, Summative\Admin\Module::class, "{$basePath}/admin", Summative\Admin\Module::BASE_NAME);
        $this->loadModule($kernel, Summative\Comment\Module::class, "{$basePath}/comments", Summative\Comment\Module::BASE_NAME);
        $this->loadModule($kernel, Summative\Report\Module::class, $basePath, Summative\Report\Module::BASE_NAME);
    }
}
