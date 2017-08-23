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
use Aura\Di\ContainerInterface as DependencyInjector;
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
        /** @var DependencyInjector $di */
        $di = $kernel->getDependencyInjector();

        $userModule = new UserModule($di);
        $userModule->init('/user');

        // Only load the external SIS academic report module if it is enabled in config

        if ($di->get('config')->get('external_document_report_enabled')) {
            $documentReportModule = new UserProfileDocumentManagementModule($di);
            $documentReportModule->init('/user/profile/documents/reports');
        }
    }
}
