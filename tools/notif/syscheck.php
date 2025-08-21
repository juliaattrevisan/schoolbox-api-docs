#!/usr/bin/php
<?php

/**
 * Safe script for determining whether all external dependencies are up and running
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Async\AMQP\AMQPConnector;
use Alaress\Lumberjack\Logger;
use Schoolbox\Application\CliTool;
use Schoolbox\Database\Factory;
use Schoolbox\Notif\Notif;
use Schoolbox\Notif\Notifactory;

error_reporting(E_ALL);

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {
    private const OPT_LONG_MAIL = 'mail';
    private const OPT_LONG_HELP = 'help';

    /** @inheritdoc */
    public function runAsRoot(): ?bool
    {
        return null;
    }

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_MAIL,
            self::OPT_LONG_HELP,
        ];
    }

    public function run(): void
    {
        $errors = array();
        $mail = $this->getOpt(self::OPT_LONG_MAIL);

        if ($this->getOpt(self::OPT_LONG_HELP)) {
            echo "Usage: syscheck.php [--mail] [--help]\n";
            exit(0);
        }

        try {
            /** @var Logger $d */
            $d = $this->getLogger();
            $d->info("Testing external dependencies are online...");

            // test database is online
            try {
                $this->getDatabaseInternal()->execute('SELECT 1');
            } catch (\Alaress\Database\Exception $e) {
                throw new PDOException("Database is not connected", 0, $e);
            }
            $d->notice('MariaDB is online', array('char' => '+'));

            try {
                // test redis cache is online
                $r = $this->getDi()->get('redis.cache');
                $r->executeCommand($r->createCommand('get', array('test')));
                $d->notice('Redis cache is online', array('char' => '+'));
            } catch (\Predis\PredisException $e) {
                $d->alert('Redis cache is offline');
                $errors[] = 'Redis cache';
            }

            try {
                // test redis storage is online
                $r = $this->getDi()->get('redis.storage');
                $r->executeCommand($r->createCommand('get', array('test')));
                $d->notice('Redis storage is online', array('char' => '+'));
            } catch (\Predis\PredisException $e) {
                $d->alert('Redis storage is offline');
                $errors[] = 'Redis storage';
            }

            try {
                // test amqp is online
                /** @var AMQPConnector $amqp */
                $amqp = $this->getDi()->get('amqp');
                $amqp->publish('Testing AMQP Connection', 'daemon.test');
                $d->notice('RabbitMQ is online', array('char' => '+'));
            } catch (Exception $e) {
                $d->alert('RabbitMQ is offline');
                $errors[] = 'RabbitMQ';
            }
        } catch (PDOException $e) {
            // Logger might not have been set up if MySQL config failed.
            // In fact, the autoloader might not even have been set up.
            if (!$d instanceof Logger && class_exists(Logger::class)) {
                $d = $this->getLogger();
            }
            $msg = 'MariaDB is offline: cannot read Redis or RabbitMQ config';
            if ($d instanceof Logger) {
                $d->alert($msg);
            } else {
                fwrite(STDERR, $msg);
            }
            $errors = array('MariaDB', 'Redis', 'RabbitMQ');
            $mail = false;
        }

        if (count($errors)) {
            if ($mail) {
                $n = Notifactory::create(Notif::TOPIC_SYSTEM);
                $n->alert("The following services are offline or uncontactable: " . implode(', ', $errors));
            }
            exit(1);
        }

        exit(0);
    }
};

$kernel = new CliTool\Kernel($app, [
    CliTool\Kernel::OPTION_ERRORS => E_ALL
], true);
$kernel->runApp();
