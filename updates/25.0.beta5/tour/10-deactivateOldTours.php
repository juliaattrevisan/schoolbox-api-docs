#!/usr/bin/php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Soft deletes old release tours
 */
$app = new class extends \Schoolbox\Tour\Prepare
{
    /**
     * The feature tours that are obsolete and scheduled for soft-deletion
     * This is just going keep on growing, but ok
     *
     * Add more dead tours here as you go
     *
     * @var array|string[]
     */
    protected array $obsoleteTitles = [
        '23.0 Release',
        '23.1 Release',
        '24.0 Release',
        '24.1 Release',
        '24.1 Admin Release',
    ];
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
