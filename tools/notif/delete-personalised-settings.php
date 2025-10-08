#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\RoleMapper;
use Schoolbox\Realtime\Admin\Helper;

require __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {
    /** @inheritDoc */
    public function getLongOpts(): array
    {
        return [
            'help',
            'role:',
            'roletype:',
            'namespace:',
        ];
    }


    /** @inheritDoc */
    public function run(): void
    {
        $msgNamespaceMapper = \Schoolbox\Realtime\Message\NSMapper::getInstance();
        $nameSpaces         = $msgNamespaceMapper->findAll()->sortBy('slug', 'asc')->getPropertyValues('slug');
        $nameSpaceList      = implode(', ', $nameSpaces);
        /* Help
         */
        if ($this->getOpt('help')) {
            echo <<<HELP

Deletes personal notification settings. This cannot be undone, once performed.

Usage: delete-personalised-settings.php [--role] [--roletype] [--namespace]
        --role: ID of a role to delete settings from
        --roletype: one of staff, parent and student
        --namespace: "all" or one of $nameSpaceList

HELP;

            exit(0);
        }

        $msgRerouteMapper = \Schoolbox\Realtime\Message\RerouteMapper::getInstance();
        $roleMapper       = RoleMapper::getInstance();

        try {
            $helper = new Helper($roleMapper, $msgRerouteMapper, $msgNamespaceMapper);
            $helper->applyDefaultSettings((int)$this->getOpt('role:'), $this->getOpt('roletype:'), $this->getOpt('namespace:'), $this->perform(), $this->getLogger());
        } catch (\Exception $e) {
            exit(0);
        }
    }
};


$kernel = new CliTool\Kernel(
    $app,
    [
        CliTool\Kernel::OPTION_LEVEL  => Monolog\Logger::CRITICAL,
        CliTool\Kernel::OPTION_ERRORS => E_ALL,
    ]
);
$kernel->runApp();
