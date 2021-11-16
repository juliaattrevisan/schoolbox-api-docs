#!/usr/bin/php
<?php

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * Copies ETHERPAD_HOST config from file to instance database
 */
$app = new class extends CliTool\App {

    public function run(): void
    {
        $tourMapper = \Schoolbox\Tour\Mapper::getInstance();
        try {
            $tour211old    = $tourMapper->findOneByColumn('title', '21.1 Release');
            $tourMapper->delete($tour211old);
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop
        }
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
