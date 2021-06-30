<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules\Custom\Mggs;

use Alaress\Application\Kernel;
use Aura\Di\Exception\ServiceNotFound;
use Config\Modules\ModuleConfigInterface;
use Custom\Mggs\Learning\Personalised\Admin\Module as AdminModule;
use Custom\Mggs\Learning\Upcoming\Module as UpcomingModule;
use Schoolbox\Learning\Mastery\Module as MasteryModule;
use Custom\Mggs\Personalised;

/**
 * Configuration for the (currently) MGGS-specific personalised learning and mastery pages.
 */
class PersonalisedLearning implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     *
     * @throws ServiceNotFound
     * @return void
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();
        if (strtoupper(trim($di->get('config')->get('site_school_code'))) === 'MGGS') {
            $masteryModule = new MasteryModule($di);
            $masteryModule ->init("/learning/mastery");

            $personalisedLearningModule = new Personalised\Module($di);
            $personalisedLearningModule->init("/learning/personalised");

            $adminModule = new AdminModule($di);
            $adminModule->init('/adminv2/pbl');

            // As of SA-10363 the upcoming work module is no longer overridden
//            // override the default upcoming module
//            $upcomingModule = new UpcomingModule($di);
//            $upcomingModule->initOverride('/learning/due');
        }
    }
}
