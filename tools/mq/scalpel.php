#!/usr/bin/php
<?php
/**
 * Search for messages that match the given pattern(s) and blast from existence.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Async\AMQP\Bunny\BunnyControl;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App{

    /** @inheritDoc */
    public function runAsRoot(): ?bool
    {
        return true;
    }

    /** @inheritDoc */
    public function getShortOpts(): array
    {
        return ['r'];
    }


    /** @inheritDoc */
    public function run(): void
    {
        $regexMode = $this->getOpt('r');

        $type = $regexMode ? 'pattern (regex' : '(string';

        $this->getLogger()->info("Surgical Removal of Messages\n");
        $this->getLogger()->warning(" ** WARNING **");
        $this->getLogger()->warning("This script will destroy messages that match the given parameters.");
        $this->getLogger()->warning("There is no 'safe mode'!\n");
        $this->getLogger()->warning("All arguments are optional, but they cannot all be blank.");

        if (!$regexMode) {
            $this->getLogger()->info("(If you need regex, use the -r flag)");
        }

        $this->getLogger()->info("");

        $printf = "%-28s: ";

        $params = array(
            'body'  => array("Bodytext $type)",             function ($v) { return $v; }),
            'href'  => array("Href $type)",                 function ($v) { return $v; }),
            'start' => array("No earlier than (strtotime)", function ($v) { return strtotime($v); }),
            'end'   => array("No later than (strtotime)",   function ($v) { return strtotime($v); })
        );

        $args = array();

        foreach ($params as $key => $prompt) {
            // remove all unprintable characters that may have been injected
            $input = trim(preg_replace('/[[:cntrl:]]+/', '', $this->readLine(sprintf($printf, $prompt[0]))));
            if ($input) {
                $args[$key] = $prompt[1]($input);
            }
        }

        $this->getLogger()->info("");

        if (!count($args)) {
            $this->getLogger()->error("No search parameters specified!");
            exit(1);
        }

        $this->getLogger()->info("Starting search & destroy...");

        $this->checkRedis($args, $regexMode);
        $this->checkAmqp($args, $regexMode);
    }


    /**
     * @param array $args
     * @param bool $regexMode
     */
    private function checkRedis($args, $regexMode): void
    {
        $redis = \Alaress\Redis::getClientInstance();

        if (!$redis) {
            $this->getLogger()->critical("Redis instance not available");
            exit(1);
        }

        // get a list of all available redis messages o.O;
        $redisList = $redis->executeCommand($redis->createCommand('keys', array('msg:*:m:*')));

        $this->getLogger()->info("Checking redis data: ");
        $acks = 0;

        foreach ($redisList as $key) {
            $compressed = $redis->executeCommand($redis->createCommand('get', array($key)));
            $uncompress = gzuncompress($compressed);

            $msg = json_decode($uncompress, true);

            if ($this->checkData($args, $regexMode, $msg)) {
                $redis->executeCommand($redis->createCommand('del', array($key)));
                ++$acks;
            }
        }

        $this->getLogger()->info("$acks messages matched and removed");
    }


    /**
     * @param array $args
     * @param bool  $regexMode
     */
    private function checkAmqp($args, $regexMode): void
    {
        // stop the socket daemon
        shell_exec('service schoolbox-socketd stop');

        $conf = $this->getConfig();

        // get a list of all available amqp queues
        $ctrl = new BunnyControl([
            'host'     => $conf->get('amqp_host'),
            'port'     => $conf->get('amqp_admin_port'),
            'username' => $conf->get('amqp_user'),
            'password' => $conf->get('amqp_pass'),
            'vhost'    => $conf->get('amqp_vhost')
        ]);
        $amqp = \Schoolbox\Notif\MQ\Connector::getInstance();

        $queueList = $ctrl->listQueues(array('name'));

        foreach ($queueList as $queue) {
            $queueName = $queue['name'];

            // only operate on unread queues
            if (!strpos($queueName, 'unread')) {
                continue;
            }

            $this->getLogger()->info(sprintf("%-32s| ", $queueName));

            $nacks = array();
            $acks  = array();

            // iterate messages
            while ($m = $amqp->readFromQueue($queueName)) {

                $data = json_decode($m->body, true);
                $msg  = json_decode($data['message'], true);

                if ($this->checkData($args, $regexMode, $msg)) {
                    $acks[] = $m;
                } else {
                    $nacks[] = $m;
                }
            }

            $this->getLogger()->info(sprintf("%4d ACK | %4d NACK\n", count($acks), count($nacks)));

            $amqp->acceptMessages($acks);
            $amqp->rejectMessages($nacks);
        }
    }


    /**
     * @param array $args
     * @param bool  $regexMode
     * @param array $msg
     * @return bool
     */
    private function checkData($args, $regexMode, $msg): bool
    {
        $success = false;

        if (isset($args['body'])) {
            $success = $success || $this->checkStr($regexMode, $args['body'], $msg['title']['text']);
        }

        if (isset($args['href'])) {
            $success = $success || $this->checkStr($regexMode, $args['href'], $msg['href']);
        }

        if (isset($args['start'])) {
            $success = $success && $this->checkTime(true, $args['start'], $msg['time']);
        }

        if (isset($args['end'])) {
            $success = $success && $this->checkTime(false, $args['start'], $msg['time']);
        }

        return $success;
    }


    /**
     * @param bool   $regexMode
     * @param string $test
     * @param string $text
     * @return bool
     */
    private function checkStr($regexMode, $test, $text): bool
    {
        if ($regexMode) {
            return (bool)preg_match("`$test`", $text);
        }
        return strpos($text, $test) !== false;
    }


    /**
     * @param bool $before
     * @param int  $test
     * @param int  $time
     * @return bool
     */
    private function checkTime($before, $test, $time): bool
    {
        if ($before) {
            return $test <= $time;
        }
        return $test >= $time;
    }
};

$kernel = new CliTool\Kernel($app, [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::INFO]);
$kernel->runApp();
