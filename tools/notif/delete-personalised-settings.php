#!/usr/bin/env php
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

$app = new class extends CliTool\BaseApp {
    protected function getName(): string
    {
        return 'delete-personalised-settings';
    }

    protected function getDescription(): string
    {
        return 'Deletes personal notification settings. This cannot be undone, once performed.';
    }

    protected function setOptions(): self
    {
        $this->addOption(null, 'role:', 'ID of a role to delete settings from');
        $this->addOption(null, 'roletype:', 'One of: staff, parent, student');
        $this->addOption(null, 'namespace:', '"all" or a specific notification namespace');
        return $this;
    }

    protected function hasPerformMode(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function run(): void
    {
        $msgNamespaceMapper = \Schoolbox\Realtime\Message\NSMapper::getInstance();
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
