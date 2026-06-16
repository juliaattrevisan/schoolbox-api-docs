#!/usr/bin/env php
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
     *
     * @var array|string[]
     */
    protected array $obsoleteTitles = [
        '23.0 Release',
        '23.1 Release',
        '24.0 Release',
        '24.1 Release',
        '25.0 Admin Release',
        '25.0 LMS and Pastoral Care Enhancements',
        '25.1 Release Course Markbook Enhancements',
        '25.1 Release General Enhancements',
        '25.1 Release Pastoral Enhancements',
        '26.0 Release General Enhancements',
    ];
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, [], true))->runApp();
