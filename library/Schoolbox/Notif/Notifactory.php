<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Notif;

use Alaress\Lumberjack;
use ConfigFallback;
use Mailer;
use PDOException;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\IConfigLoader;
use Symfony\Component\Mailer\Transport\TransportInterface;

class Notifactory
{

    /** @var IConfigLoader */
    protected $conf;

    /** @var TransportInterface */
    protected $swift;

    /** @var LoggerInterface */
    protected $log;


    /**
     * Wrapper method for compatibility. Replaces old Notif::__construct($topic, $configLoader)
     *
     * @param string        $topic
     * @param IConfigLoader $configLoader
     * @return NotifInterface
     */
    public static function create($topic = Notif::TOPIC_SYSTEM, IConfigLoader $configLoader = null)
    {
        $self = new self($configLoader);

        return $self->generateNotif($topic);
    }


    /**
     * Constructor
     *
     * @param IConfigLoader|null      $configLoader
     * @param TransportInterface|null $swift
     * @param LoggerInterface|null    $log
     */
    public function __construct(IConfigLoader $configLoader = null, TransportInterface $swift = null, LoggerInterface $log = null)
    {
        $this->setConfigLoader($configLoader);
        $this->setSwiftTransport($swift);
        $this->setLogger($log);
    }


    /**
     * @param string $topic
     * @return NotifInterface
     */
    public function generateNotif($topic = Notif::TOPIC_SYSTEM)
    {
        return new Notif($topic, $this->generateHandlerFactory());
    }


    /**
     * @return HandlerFactory
     */
    public function generateHandlerFactory()
    {
        return new HandlerFactory($this->conf, $this->swift, $this->log);
    }


    /**
     * @param IConfigLoader $conf
     * @return self
     */
    public function setConfigLoader(IConfigLoader $conf = null)
    {
        if ($conf) {
            // use the provided config
            $this->conf = $conf;
        } elseif (extension_loaded('pdo')) {
            try {
                // try for a config mapper instance
                Database::reinitialise();

                $this->conf = ConfigLoader::getInstance();
                $this->conf->get('mail_support_address');
            } catch (PDOException $e) {
                // if something PDO-esque broke, fall back
                $this->conf = new ConfigFallback();
            }
        } else {
            // fallback to hardcoded config vars
            $this->conf = new ConfigFallback();
        }

        return $this;
    }


    /**
     * @param TransportInterface $swift
     * @return self
     */
    public function setSwiftTransport(TransportInterface $swift = null)
    {
        $this->swift = $swift ?: Mailer::getConfiguredSwiftTransport($this->conf, true);
        return $this;
    }


    /**
     * @param LoggerInterface $log
     * @return self
     */
    public function setLogger(LoggerInterface $log = null)
    {
        $this->log = $log ?: new Lumberjack\Logger(null);
        return $this;
    }
}
