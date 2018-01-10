<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Config\Modules\Custom\Mggs;

use Alaress\Application\Kernel;
use Aura\Di\Exception\ServiceNotFound;
use Config\Modules\ModuleConfigInterface;
use Custom\Mggs\Learning\Personalisad\Admin\Module as AdminModule;
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
        if ($di->get('config')->get('site_location') === 'mggs') {
            $masteryModule = new MasteryModule($di);
            $masteryModule ->init("/learning/mastery");

            $personalisedLearningModule = new Personalised\Module($di);
            $personalisedLearningModule->init("/learning/personalised");

            $adminModule = new AdminModule($di);
            $adminModule->init('/adminv2/pbl');
        }
    }
}
