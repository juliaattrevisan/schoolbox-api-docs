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
        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            $helpText = <<<HELP
            Task daemon notifier

            notify.php --from=SENDER --to=RECIPIENT --msg=MESSAGE

            -h, --help: Output this help message and exit
            --from:     User which sends the message.
            --to:       User which receives the message.
                        (Currently this test harness cannot send to the "%s" user.)
            -m, --msg:  Message body

            HELP;
            printf($helpText, UserMapper::USERNAME_ALARESS_ADMIN);
            exit;
        }

        $fromUserId     = $this->getOpt(self::OPT_LONG_FROM);
        $toUserId       = $this->getOpt(self::OPT_LONG_TO);
        $messageBody    = $this->getOpt(self::OPT_LONG_MESSAGE) ?? $this->getOpt(self::OPT_SHORT_MSG);
        if (!isset($fromUserId, $toUserId, $messageBody)) {
            fwrite(STDERR, "The following options must be provided: `--from`, `--to`, `-m|--msg`\m");
            exit(1);
        }

        // Get the taskd context, and send
        /** @var Producer $producer */
        $producer = $this->getDi()->get('taskd.messageProducer');

        /** @var MessageFactory $messageFactory */
        $messageFactory = $this->getDi()->get('taskd.messageFactory');

        $userMapper = UserMapper::getInstance();
        $fromUser   = $userMapper->findById($fromUserId);
        $toUser     = $userMapper->findById($toUserId);

        // TODO this should be a system user, instead of an admin
        $adminUser      = $userMapper->findByUsername(UserMapper::USERNAME_ALARESS_ADMIN);
        $userSerializer = new ModelToArray();
        $message        = $messageFactory->build(
            'notify',
            [],
            (new Factory($this->getConfig()))
                ->buildFullyQualified()
                ->setPath(sprintf('/api/user/%d/notify', $toUserId))
                ->toString(),
            [
                'message'   => $messageBody,
                'from'      => call_user_func($userSerializer, $fromUser),
                'to'        => call_user_func($userSerializer, $toUser),
            ],
            $adminUser,
        );

        $producer->send($message);
    }
});
$kernel->runApp();
