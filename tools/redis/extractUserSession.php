#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */

declare(strict_types=1);

use Predis\Collection\Iterator\Keyspace;
use Schoolbox\Application\CliTool;
use Schoolbox\Realtime\Pipe\PipeManager;
use Schoolbox\User;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * Extract user session
 */
$app = new class extends CliTool\App {
    private const OPT_SHORT_HELP = 'h';
    private const OPT_LONG_HELP  = 'help';

    private const OPT_SHORT_USER_ID   = 'u:';
    private const OPT_SHORT_REDIS_KEY = 'k:';

    /**
     * @inheritdoc
     */
    public function getShortOpts(): array
    {
        return [
            self::OPT_SHORT_HELP,
            self::OPT_SHORT_USER_ID,
            self::OPT_SHORT_REDIS_KEY,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getLongOpts(): array
    {
        return [self::OPT_LONG_HELP];
    }


    /**
     * @inheritDoc
     */
    public function run(): void
    {
        $this->deprecationNotice("SA-19334");

        /** @var \Predis\ClientInterface $redis */
        $redis = $this->getDi()->get('redis.storage');

        if ($this->getOpt(self::OPT_LONG_HELP) || $this->getOpt(self::OPT_SHORT_HELP)) {
            echo <<<HELP
            Synopsis:
                Extract user session in redis for a user.

            Usage:
                ./extractUserSession.php [-u userId] [-k redisKey]

            Options:
                -h, --help:
                    Print this help text.

            HELP;
            exit;
        }
//        if (!$this->getOpt(self::OPT_SHORT_USER_ID) && !$this->getOpt(self::OPT_SHORT_REDIS_KEY)) {
//            $this->getLogger()->warning('-u or -k is required.');
//            exit();
//        }

        $match      = PipeManager::REDIS_SESSION_PREFIX . '*';
        $targetUser = null;

        if ($this->getOpt(self::OPT_SHORT_USER_ID)) {
            $userId = (int)$this->getOpt(self::OPT_SHORT_USER_ID);
            try {
                $targetUser = \Schoolbox\UserMapper::getInstance()->findById($userId);
            } catch (\Schoolbox\Mapper\Exception) {
                $this->getLogger()->error("Could not find a user with ID: {$userId}");
                exit();
            }
        } elseif ($this->getOpt(self::OPT_SHORT_REDIS_KEY)) {
            $match = $this->getOpt(self::OPT_SHORT_REDIS_KEY);
        }

        // session
        $search = new Keyspace($redis, $match);
        // Decode the session data.
        // Unfortunately a session must be started in order to do this.
        // Providing tmp dir as session path
        $keys = [];
        session_save_path(sys_get_temp_dir());
        session_start();
        foreach ($search as $key) {
            $redisSession = $redis->get($key);
            if (!$redisSession) {
                continue;
            }
            session_decode($redisSession);
            /** @var User|null $redisSessionUser */
            $redisSessionUser = $_SESSION['user'] ?? null;

            if (!$redisSessionUser || ($targetUser && ($redisSessionUser->getId() !== $targetUser->getId()))) {
                session_unset();
                continue;
            }
            fwrite($this->getOutStream(), "================================================================\n");

            // $_SESSION
            foreach ($_SESSION as $sessionKey => $sessionVal) {
                if (!in_array($sessionKey, $keys)) {
                    $keys[] = $sessionKey;
                }
                if (is_array($sessionVal)) {
                    $sessionValString = "[" . count($sessionVal) . "]";
                } elseif (is_object($sessionVal)) {
                    $sessionValString = $sessionVal::class;
                } else {
                    $sessionValString = $sessionVal;
                }
                fwrite(
                    $this->getOutStream(),
                    sprintf("%s: %s\n", $sessionKey, $sessionValString)
                );
            }
            $sessionUserSize = strlen(serialize($redisSessionUser));
            fwrite($this->getOutStream(), "== User object ==\n");
            fwrite($this->getOutStream(), sprintf("totalsize: %s\n", $sessionUserSize));
            fwrite($this->getOutStream(), sprintf("username: %s\n", $redisSessionUser->getUsername()));
            fwrite($this->getOutStream(), sprintf("role: %s\n", $redisSessionUser->getRole()->getType()));
            fwrite($this->getOutStream(), sprintf("subject: %s\n", (string)$redisSessionUser->getSubjectCollection()?->count()));
            fwrite($this->getOutStream(), sprintf("child: %s\n", (string)$redisSessionUser->getChildren()?->count()));
            fwrite($this->getOutStream(), sprintf("teacher: %s\n", (string)$redisSessionUser->getTeacherCollection()?->count()));
            fwrite($this->getOutStream(), sprintf("userAccess: %s\n", (string)$redisSessionUser->getAccessList()->count()));
            fwrite($this->getOutStream(), sprintf("TTL: %s\n", $redis->ttl($key)));
            fwrite($this->getOutStream(), sprintf("summary: %s,%s,%s,%s,%s,%s\n", $key, $redisSessionUser->getUsername(), $sessionUserSize, $redisSessionUser->getRole()->getType(), $_SESSION['lasttime'], $_SESSION['ip']));
            session_unset();
        }
        session_abort();

        $this->deprecationNotice("SA-19334");
    }
};

$kernel = new CliTool\Kernel(
    $app,
    [
        CliTool\Kernel::OPTION_LEVEL  => Monolog\Logger::INFO,
        CliTool\Kernel::OPTION_ERRORS => E_ALL,
    ]
);
$kernel->runApp();
