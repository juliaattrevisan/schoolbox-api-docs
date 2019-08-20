<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Import\IntroModule;

/**
 * Configuration for admin import module
 *
 * @package Config\Modules
 */
class AdminImport implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function configure($kernel)
    {
        $di = $kernel->getDependencyInjector();

        /* Introduction to Admin Importers
         */
        (new IntroModule($di))->init('/adminv2/import');

        /* User Photos
         */
        (new \Schoolbox\Admin\Photos\Module($di))->init('/adminv2/photos');

        /* User Profile
         */
        (new \Schoolbox\Admin\Import\Profile\Module($di))->init('/adminv2/import/profile');

        /* Pastoral Care
         */
        if ($di->get('config')->get('pastoral_enabled')) {
            (new \Schoolbox\Admin\Import\Pastoral\Module($di))->init('/adminv2/import-pastoral');
        }
    }
}
