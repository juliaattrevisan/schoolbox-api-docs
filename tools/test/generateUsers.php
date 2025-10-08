#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;

require __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {
    private const NUM_RECORDS = 20000;

    /** @inheritDoc */
    public function run(): void
    {
        $this->deprecationNotice("SA-19334");

        $userMapper = new UserMapper();

        $roleMapper   = new RoleMapper();
        $roles        = $roleMapper->findAll();
        $campusMapper = new \Schoolbox\CampusMapper();
        $campuses     = $campusMapper->findAll();

        $max = ceil(self::NUM_RECORDS / $roles->count());
        foreach ($roles as $role) {
            for ($j = 0; $j < $max; $j++) {
                $user = $userMapper->createModel()
                                   ->setUsername($role->getName() . $j)
                                   ->setFirstname(ucfirst($role->getName() . $j))
                                   ->setRole($role)
                    ->setLocalEmail(true)
                    ->setUseInternalMail(true)
                    ->setEportfolioEnable(false)
                                   ->setCampusCollection($campuses);
                $userMapper->save($user);
            }
        }

        $this->deprecationNotice("SA-19334");
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
