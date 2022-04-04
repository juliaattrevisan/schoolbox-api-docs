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
use Schoolbox\Url\Factory;
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
        $adminUser      = UserMapper::getInstance()->findByUsername(UserMapper::USERNAME_ALARESS_ADMIN);
        $userSerializer = new ModelToArray();
        $message        = $messageFactory->build(
            'notify',
            [
                'message'   => 'Hi',
                'from'      => call_user_func($userSerializer, $adminUser),
                'to'        => call_user_func($userSerializer, $adminUser),
            ],
            (new Factory($this->getConfig()))
                ->buildFullyQualified()
                ->setPath('/notify/')
                ->setQuery(['userId' => $adminUser->getId()])
                ->toString(),
            $adminUser,
        );

        $producer->send($message);
    }
});
$kernel->runApp();
