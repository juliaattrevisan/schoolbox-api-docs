#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\TaskDaemon\Producer;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(new class extends CliTool\App {
    public function run(): void
    {
        // Get the taskd context, and send
        /** @var Producer $producer */
        $producer = $this->getDi()->get('taskd.messageProducer');
        $producer->send(json_encode(['message' => 'message',]));
    }
});
$kernel->runApp();
