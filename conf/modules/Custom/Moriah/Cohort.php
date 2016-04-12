<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Config\Modules\Custom\Moriah;

use Alaress\Application\Kernel;
use Custom\Moriah\Cohort\Module;
use Config\Modules\ModuleConfigInterface;
use Schoolbox\UserMapper;

/**
 * Configuration for the Moriah-specific cohort report.
 *
 * @package Config\Modules\Custom\Moriah
 */
class Cohort implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     *
     * @return void
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();
        if ($di->get('config')->get('site_location') == 'moriah') {
            $module = new Module($di, $di->newInstance(UserMapper::class));
            $module->init('/modules/custom/moriah/cohort');
        }
    }
}
