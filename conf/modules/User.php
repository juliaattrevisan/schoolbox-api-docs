<?php
/**
 * Configuration for the curriculum mapping module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Schoolbox\User\Module as UserModule;
use Schoolbox\User\Profile\DocumentManagement\Module as UserProfileDocumentManagementModule;

class User implements ModuleConfigInterface
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

        $module = new UserModule($kernel->getDependencyInjector());
        $module->init('/user');

        if ($di->get('config')->get('external_document_report_enabled')) {
            $module = new UserProfileDocumentManagementModule($kernel->getDependencyInjector());
            $module->init('/user/profile/documents');
        }
    }
}
