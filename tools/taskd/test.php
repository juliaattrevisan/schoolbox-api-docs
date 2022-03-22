#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Enqueue\Client\Message;
use Schoolbox\Application\CliTool;
use Schoolbox\TaskDaemon\ClientFactory;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(new class extends CliTool\App {
    public function run(): void
    {
        // Get the taskd client
        $client = (new ClientFactory())->build($this->getConfig()->get('redis_url'));

        // Do something with the client
        $message = new Message(json_encode(['message' => 'message',]));
        $message->setMessageId(uniqid());
        $client->sendEvent('taskd_queue', $message);
    }
});
$kernel->runApp();
