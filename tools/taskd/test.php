#!/usr/bin/php
<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\MessageFactory;
use Schoolbox\User\Translator\Author\ModelToArray;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new CliTool\Kernel(new class extends CliTool\App {
    public function run(): void
    {
        // Get the taskd context, and send
        /** @var Producer $producer */
        $producer = $this->getDi()->get('taskd.messageProducer');

        /** @var MessageFactory $messageFactory */
        $messageFactory = $this->getDi()->get('taskd.messageFactory');
        $message        = $messageFactory->build(
            'notify',
            [
                'message' => 'Hi',
                'sender'  => call_user_func(
                    new ModelToArray(),
                    UserMapper::getInstance()->findByUsername(UserMapper::USERNAME_ALARESS_ADMIN),
                ),
            ],
            null
        );

        $producer->send($message);
    }
});
$kernel->runApp();
