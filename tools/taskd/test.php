#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\TaskDaemon\ContextFactory;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(new class extends CliTool\App {
    public function run(): void
    {
        // Get the taskd context
        $context = (new ContextFactory())->build($this->getConfig()->get(ConfigMapper::NAME_TASKD_REDIS_URL));

        // Send a message
        $message = $context->createMessage(json_encode(['message' => 'message',]));
        $queue   = $context->createQueue($this->getConfig()->get(ConfigMapper::NAME_TASKD_REDIS_QUEUE));
        $context->createProducer()->send($queue, $message);
    }
});
$kernel->runApp();
