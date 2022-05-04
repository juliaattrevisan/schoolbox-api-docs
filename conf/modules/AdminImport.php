<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Config\ModuleLoader;
use Psr\Container\ContainerInterface;
use Schoolbox\Admin\Import;

/**
 * Configuration for admin import module
 *
 * @package Config\Modules
 */
class AdminImport extends ModuleLoader
{
    /**
     * @inheritdoc
     */
    public function configure(ContainerInterface $di): void
    {
        /* Introduction to Admin Importers
         */
        $this->loadModule($di, Import\Module::class, '/adminv2/import');

        /**
         * Direct Import
         */

        /* User Accounts
         */
        $this->loadModule($di, Import\UserAccount\Module::class, '/adminv2/import/userAccount');

        /* User Photos
         */
        $this->loadModule($di, Import\Photos\Module::class, '/adminv2/import/photos');

        /* Pastoral Care
         */
        if ($di->get('config')->get('pastoral_enabled')) {
            $this->loadModule($di, Import\Pastoral\Module::class, '/adminv2/import/pastoral');
        }

        /* Import folders
         * (bulk add folders)
         */
        $this->loadModule($di, Import\Folder\Module::class, '/adminv2/import/folder');

        /**
         * School Data
         */

        /* User Profile
         */
        $this->loadModule($di, Import\Profile\Module::class, '/adminv2/import/profile');

        /* Timetable Structure
         */
        $this->loadModule($di, Import\TimetableStructure\Module::class, '/adminv2/import/timetableStructure');

        /* Timetable
         */
        $this->loadModule($di, Import\Timetable\Module::class, '/adminv2/import/timetable');

        /* Timetable Enrolment
         */
        $this->loadModule($di, Import\TimetableEnrolment\Module::class, '/adminv2/import/timetableEnrolment');
    }
}
