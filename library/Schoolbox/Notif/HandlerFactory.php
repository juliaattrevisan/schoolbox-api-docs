<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Notif;

use InvalidArgumentException;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use RangeException;
use RuntimeException;
use Schoolbox\Admin\EmailDKIM\Mapper as DKIMMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Mail\EmailFactory;
use Schoolbox\Notif\Handler\MySQLHandler;
use Schoolbox\Notif\Handler\SymfonyMailerHandler;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\RfcComplianceException;

class HandlerFactory
{
    const FALLBACK_EMAIL     = 'webmaster+schoolboxnotif@alaress.com.au';

    const SYS_FATAL_MAIL     = 'fatalmailer';
    const SYS_FATAL_LOG      = 'fatallogger';
    const SYS_MAIL           = 'sysmailer';
    const SYS_LOG            = 'syslogger';
    const SYS_EXCEPTION_MAIL = 'exceptionmailer';
    const SYS_NULL_DEBUG     = 'nulldebug';
    const SYS_DEBUG_MAIL     = 'debugmailer';

    const LOG_AUTH           = Notif::TOPIC_AUTH;
    const LOG_CMS            = Notif::TOPIC_CMS;


    /** @var IConfigLoader */
    protected $conf;

    /** @var TransportInterface */
    protected $transport;

    /** @var LoggerInterface */
    protected $log;

    /** @var HandlerInterface|null[] */
    protected $handlers = [];

    /** @var callable[] */
    protected $handlerGenerators;


    /**
     * HandlerFactory constructor. Loads up a whole bunch of lazy-loaded handlers.
     * TODO: investigate putting all these handlers into DI
     *
     * @param IConfigLoader      $conf
     * @param TransportInterface $transport
     * @param LoggerInterface    $log
     */
    public function __construct(IConfigLoader $conf, TransportInterface $transport, LoggerInterface $log)
    {
        $this->conf      = $conf;
        $this->transport = $transport;
        $this->log       = $log;

        $this->handlerGenerators[self::SYS_FATAL_MAIL] = function () {
            return $this->createMailHandler('Schoolbox is Down', Logger::EMERGENCY);
        };
        $this->handlerGenerators[self::SYS_FATAL_LOG] = function () {
            // If we are in web context, this const will not exist, just insert a placeholder handler
            // that will never consume the record, thus bubbling to the next handler in the stack
            return defined('STDERR')
                ? new StreamHandler(STDERR, Logger::EMERGENCY, false)
                : new SkipHandler();
        };
        $this->handlerGenerators[self::SYS_MAIL] = function () {
            return $this->createMailHandler('Schoolbox Requires Your Attention', Logger::ALERT);
        };
        $this->handlerGenerators[self::SYS_LOG] = function () {
            // If we are in web context, this const will not exist, just insert a placeholder handler
            // that will never consume the record, thus bubbling to the next handler in the stack
            return defined('STDERR')
                ? new StreamHandler(STDERR, Logger::ALERT, false)
                : new SkipHandler();
        };
        $this->handlerGenerators[self::SYS_EXCEPTION_MAIL] = function () {
            return new NullHandler(Logger::CRITICAL);
        };
        $this->handlerGenerators[self::SYS_NULL_DEBUG] = function () {
            return new NullHandler(Logger::INFO);
        };
        $this->handlerGenerators[self::SYS_DEBUG_MAIL] = function () {
            return $this->createMailHandler('Schoolbox Debug', Logger::DEBUG, false);
        };
        $this->handlerGenerators[self::LOG_AUTH] = function () {
            return $this->createSqlHandler(self::LOG_AUTH, Logger::NOTICE, false);
        };
        $this->handlerGenerators[self::LOG_CMS] = function () {
            return $this->createSqlHandler(self::LOG_CMS, Logger::NOTICE, false);
        };
    }


    /**
     * Set the logger for this particular
     * @param LoggerInterface $log
     * @return static
     */
    public function setLogger($log)
    {
        $this->log = $log;
        return $this;
    }


    /**
     *
     * @param string $topic
     * @throws InvalidArgumentException if the topic given is not system
     * @return HandlerInterface
     */
    public function getFileLogHandler($topic)
    {
        if (!is_dir($this->conf->get('logs_dir'))) {
            if (!mkdir($concurrentDirectory = $this->conf->get('logs_dir'), 0775, true) && !is_dir($concurrentDirectory)) {
                throw new RuntimeException("Cannot create log directory: $concurrentDirectory");
            }
        }
        $logfile = sprintf("%s/%s.log", $this->conf->get('logs_dir'), $topic);

        if (is_resource($lp = fopen($logfile, 'ab'))) {
            return new StreamHandler($lp, Logger::INFO, false);
        }

        throw new InvalidArgumentException("Cannot open syslog '$logfile'.");
    }

    /**
     * @param string $tag
     * @return HandlerInterface|null
     */
    public function getHandler($tag)
    {
        if (!array_key_exists($tag, $this->handlers)) {
            $this->handlers[$tag] = is_callable($this->handlerGenerators[$tag] ?? null)
                ? $this->handlerGenerators[$tag]()
                : null;
        }
        return $this->handlers[$tag];
    }


    /**
     * Get a stack of handlers
     *
     * @param string[] $tags
     * @param bool     $suppressFailures
     * @throws InvalidArgumentException
     * @return HandlerInterface[]
     */
    public function getStack(array $tags, $suppressFailures = true)
    {
        $stack = [];

        foreach ($tags as $tag) {
            $handler = null;
            if ($suppressFailures) {
                try {
                    $handler = $this->getHandler($tag);
                } catch (InvalidArgumentException $e) {
                    $this->log->alert($e->getMessage(), [ 'exception' => $e ]);
                }
            } else {
                // exceptions not being suppressed
                $handler = $this->getHandler($tag);
            }
            if (isset($handler)) {
                $stack[] = $handler;
            }
        }

        return $stack;
    }


    /**
     * @param string $title
     * @param string $to
     * @param string|array $from
     * @param int $severity
     * @param bool $bubble
     * @return SymfonyMailerHandler
     */
    protected function createMailHandler($title, $severity, $bubble = true, $to = null, $from = null): SymfonyMailerHandler
    {
        $to   = $to ?: $this->conf->get('mail_support_address');
        $from = $from ?: [
            $this->conf->get('mail_from_address'),
            $this->conf->get('mail_from_name')
        ];

        $mailFactory = new EmailFactory(
            $this->conf,
            DKIMMapper::getInstance(),
        );

        try {
            $toAddress = new Address($to);
        } catch (RfcComplianceException $e) {
            $toAddress = new Address(self::FALLBACK_EMAIL);
        }

        $mail = $mailFactory->buildSimpleEmail($title, $toAddress);
        $mail->setLogger($this->log);

        try {
            if (is_array($from)) {
                $from = new Address($from[0], $from[1] ?? '');
            } else {
                $from = new Address($from);
            }
        } catch (RfcComplianceException $e) {
            $from = new Address(self::FALLBACK_EMAIL);
        }
        $mail->setFrom($from->getAddress(), $from->getName());

        return new SymfonyMailerHandler(
            $mail,
            $severity,
            $bubble
        );
    }


    /**
     * Only returns an SQL handler if it's relevant to the topic
     *
     * @param string $topic
     * @param int    $level
     * @param bool   $bubble
     * @throws RangeException
     * @throws InvalidArgumentException
     * @return MySQLHandler
     */
    protected function createSqlHandler($topic, $level, $bubble = true)
    {
        // can't give you an SQL handler if there ain't no SQL
        if (!extension_loaded('pdo')) {
            throw new RangeException("PDO is not loaded; cannot initialise database connection");
        }

        $sql = new MySQLHandler($level, $bubble);

        switch ($topic) {
            // when someone logs in, or fails to
            case Notif::TOPIC_AUTH:
                $sql->setQuery("
                    INSERT INTO `login_log`
                        (`username`, `date`, `ip`, `login`, `jwt_id`)
                    VALUES
                        (:username, NOW(), :ip, :login, :jwtId)
                ");
                return $sql;

            // when cms-specific things are logged
            case Notif::TOPIC_CMS:
                $sql->setQuery("
                    INSERT INTO `cmsLog`
                        (`object`, `objectId`, `action`, `user`)
                    VALUES
                        (:object, :objectId, :action, :user)
                ");
                return $sql;
        }

        throw new InvalidArgumentException("Cannot handle topic '$topic' with SQL.");
    }
}
