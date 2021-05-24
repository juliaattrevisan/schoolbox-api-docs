<?php

/**
 * Configuration for the curriculum mapping module.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Config\Modules;

use Alaress\Application\Kernel;
use Aura\Di\ContainerInterface as DependencyInjector;
use Schoolbox\User\Module as UserModule;
use Schoolbox\User\Password\Change\Module as ChangePasswordModule;
use Schoolbox\User\Password\Reset\Module as ResetPasswordModule;
use Schoolbox\User\Profile\DocumentManagement\Module as UserProfileDocumentManagementModule;
use Schoolbox\User\Token\Module as UserTokenModule;

class User implements ModuleConfigInterface
{
    /**
     * Run configuration for the given module
     *
     * @param Kernel $kernel
     *
     * @return void
     * @throws \Aura\Di\Exception\ServiceNotFound
     */
    public function configure($kernel): void
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

        /* Change Password
         */
        $module = new ChangePasswordModule($di);
        $module->init('/passwordChange');

        /* Reset Password
         */
        $module = new ResetPasswordModule($di);
        $module->init('/passwordReset');

        // Token
        $module = new UserTokenModule($di);
        $module->init('/user/token');
    }
}
