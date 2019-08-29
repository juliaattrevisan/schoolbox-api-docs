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
use Monolog\Handler\SwiftMailerHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use RangeException;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\Handler\MySQLHandler;
use Swift_Mailer;
use Swift_Message;
use Swift_RfcComplianceException;
use Swift_Transport;

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

    /** @var Swift_Transport */
    protected $swift;

    /** @var LoggerInterface */
    protected $log;

    /** @var HandlerInterface[] */
    protected $handlers;

    /** @var callable[] */
    protected $handlerGenerators;


    /**
     * HandlerFactory constructor. Loads up a whole bunch of lazy-loaded handlers.
     * TODO: investigate putting all these handlers into DI
     *
     * @param IConfigLoader   $conf
     * @param Swift_Transport $swift
     * @param LoggerInterface $log
     */
    public function __construct(IConfigLoader $conf, Swift_Transport $swift, LoggerInterface $log)
    {
        $this->conf       = $conf;
        $this->swift      = $swift;
        $this->log        = $log;

        $this->handlerGenerators[self::SYS_FATAL_MAIL] = function ()
        {
            return $this->createMailHandler('Schoolbox is Down', Logger::EMERGENCY);
        };
        $this->handlerGenerators[self::SYS_FATAL_LOG] = function ()
        {
            // If we are in web context, this const will not exist, just insert a placeholder handler
            // that will never consume the record, thus bubbling to the next handler in the stack
            return defined('STDERR')
                ? new StreamHandler(STDERR, Logger::EMERGENCY, false)
                : new SkipHandler();
        };
        $this->handlerGenerators[self::SYS_MAIL] = function ()
        {
            return $this->createMailHandler('Schoolbox Requires Your Attention', Logger::ALERT);
        };
        $this->handlerGenerators[self::SYS_LOG] = function ()
        {
            // If we are in web context, this const will not exist, just insert a placeholder handler
            // that will never consume the record, thus bubbling to the next handler in the stack
            return defined('STDERR')
                ? new StreamHandler(STDERR, Logger::ALERT, false)
                : new SkipHandler();
        };
        $this->handlerGenerators[self::SYS_EXCEPTION_MAIL] = function ()
        {
            return new NullHandler(Logger::CRITICAL);
        };
        $this->handlerGenerators[self::SYS_NULL_DEBUG] = function ()
        {
            return new NullHandler(Logger::INFO);
        };
        $this->handlerGenerators[self::SYS_DEBUG_MAIL] = function ()
        {
            return $this->createMailHandler('Schoolbox Debug', Logger::DEBUG, false);
        };
        $this->handlerGenerators[self::LOG_AUTH] = function ()
        {
            return $this->createSqlHandler(self::LOG_AUTH, Logger::NOTICE, false);
        };
        $this->handlerGenerators[self::LOG_CMS] = function ()
        {
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
        $logfile = sprintf("%s/%s.log", $this->conf->get('logs_dir'), $topic);

        if (is_resource($lp = fopen($logfile, 'ab'))) {
            return new StreamHandler($lp, Logger::INFO, false);
        }

        throw new InvalidArgumentException("Cannot open syslog '$logfile'.");
    }

    /**
     * @param $tag
     * @return HandlerInterface
     */
    public function getHandler($tag)
    {
        if (empty($this->handlers[$tag])) {
            $this->handlers[$tag] = $this->handlerGenerators[$tag]();
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

            if ($suppressFailures) {
                try {
                    array_push($stack, $this->getHandler($tag));
                } catch (InvalidArgumentException $e) {
                    $this->log->alert($e->getMessage(), [ 'exception'=>$e ]);
                }
            } else {
                // exceptions not being suppressed
                array_push($stack, $this->getHandler($tag));
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
     * @return SwiftMailerHandler
     */
    protected function createMailHandler($title, $severity, $bubble = true, $to = null, $from = null)
    {
        $to   = $to ?: $this->conf->get('mail_support_address');
        $from = $from ?: [
            $this->conf->get('mail_from_address'),
            $this->conf->get('mail_from_name')
        ];

        $mail = new Swift_Message($title, null, 'text/plain', 'utf-8');

        try {
            $mail->setTo($to);
        }
        catch (Swift_RfcComplianceException $e) {
            $mail->setTo(self::FALLBACK_EMAIL);
        }

        try {
            if (is_array($from)) {
                $mail->setFrom($from[0], $from[1]);
            } else {
                $mail->setFrom($from);
            }
        } catch (Swift_RfcComplianceException $e) {
            $mail->setFrom(self::FALLBACK_EMAIL);
        }

        return new SwiftMailerHandler(
            new Swift_Mailer(
                $this->swift
            ), $mail, $severity, $bubble
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
        if(!extension_loaded('pdo')) {
            throw new RangeException("PDO is not loaded; cannot initialise database connection");
        }

        $sql = new MySQLHandler($level, $bubble);

        switch($topic) {
            // when someone logs in, or fails to
            case Notif::TOPIC_AUTH:
                $sql->setQuery("
                    INSERT INTO `login_log`
                        (`username`, `date`, `ip`, `login`)
                    VALUES
                        (:username, NOW(), :ip, :login)
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
