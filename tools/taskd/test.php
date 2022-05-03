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

$kernel = new CliTool\Kernel(new class extends CliTool\App
{

    private const OPT_SHORT_HELP = 'h';
    private const OPT_SHORT_MSG  = 'm:';

    private const OPT_LONG_HELP    = 'help:';
    private const OPT_LONG_FROM    = 'from:';
    private const OPT_LONG_TO      = 'to:';
    private const OPT_LONG_MESSAGE = 'msg:';

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [self::OPT_SHORT_HELP, self::OPT_SHORT_MSG];
    }

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP, self::OPT_LONG_FROM, self::OPT_LONG_TO, self::OPT_LONG_MESSAGE];
    }

    /**
     * @inheritdoc
     */
    public function run(): void
    {
        // Get the taskd context, and send
        /** @var Producer $producer */
        $producer = $this->getDi()->get('taskd.messageProducer');

        /** @var MessageFactory $messageFactory */
        $messageFactory = $this->getDi()->get('taskd.messageFactory');
        $message        = $messageFactory->build(
            'test',
            [],
            null,
            [],
            null,
        );

        $producer->send($message);
    }
});
$kernel->runApp();
