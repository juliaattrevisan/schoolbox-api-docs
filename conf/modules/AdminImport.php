<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\Admin\Import;

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
    public function configure($kernel): void
    {
        $di = $kernel->getDependencyInjector();

        /* Introduction to Admin Importers
         */
        (new Import\Module($di))->init('/adminv2/import');

        /* User Photos
         */
        (new Import\Photos\Module($di))->init('/adminv2/import/photos');

        /* User Profile
         */
        (new Import\Profile\Module($di))->init('/adminv2/import/profile');

        /* Pastoral Care
         */
        if ($di->get('config')->get('pastoral_enabled')) {
            (new Import\Pastoral\Module($di))->init('/adminv2/import-pastoral');
        }

        /* User Accounts
         */
        (new Import\UserAccount\Module($di))->init('/adminv2/import/userAccount');

        /* Timetable Structure
         */
        (new Import\TimetableStructure\Module($di))->init('/adminv2/import/timetableStructure');

        /* Timetable
         */
        (new Import\Timetable\Module($di))->init('/adminv2/import/timetable');
    }
}
