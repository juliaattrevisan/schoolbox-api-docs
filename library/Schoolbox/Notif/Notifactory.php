<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Notif;


use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Lumberjack;
use Alaress\Redis;
use ConfigFallback;
use Database;
use Mailer;
use PDOException;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use Predis\ClientInterface;
use Psr\Log\LoggerInterface;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Notif\MQ\Connector as MQConnector;
use Swift_Transport;

class Notifactory
{

    /** @var string */
    protected $instanceId;
    /** @var IConfigLoader */
    protected $conf;
    /** @var ClientInterface */
    protected $redis;
    /** @var AMQPInterface */
    protected $mqc;
    /** @var Swift_Transport */
    protected $swift;
    /** @var LoggerInterface */
    protected $log;


    /**
     * Wrapper method for compatibility. Replaces old Notif::__construct($topic, $configLoader)
     *
     * @param string        $topic
     * @param IConfigLoader $configLoader
     * @param string        $instanceId
     * @return NotifInterface
     */
    public static function create($topic = Notif::TOPIC_SYSTEM, IConfigLoader $configLoader = null, $instanceId = INSTANCE_ID)
    {
        $self = new self($configLoader, $instanceId);

        return $self->generateNotif($topic);
    }


    /**
     * Notifactory constructor.
     *
     * @param IConfigLoader   $configLoader
     * @param string          $instanceId
     * @param AMQPInterface   $mqc
     * @param ClientInterface $redis
     * @param Swift_Transport $swift
     * @param LoggerInterface $log
     */
    public function __construct(IConfigLoader $configLoader = null, $instanceId = INSTANCE_ID, AMQPInterface $mqc = null, ClientInterface $redis = null, Swift_Transport $swift = null, LoggerInterface $log = null)
    {
        $this->setConfigLoader($configLoader);
        $this->setRedis($redis);
        $this->setAMQP($mqc);
        $this->setSwiftTransport($swift);
        $this->setLogger($log);
        $this->setInstanceId($instanceId);
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
        return new HandlerFactory($this->instanceId, $this->conf, $this->swift, $this->log, $this->redis, $this->mqc);
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

                $this->conf = ConfigMapper::getInstance();
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
     * @param ClientInterface $redis
     * @return $this
     */
    public function setRedis(ClientInterface $redis = null)
    {
        $this->redis = $redis ?: Redis::getClientInstance();
        return $this;
    }


    /**
     * @param AMQPInterface $mqc
     * @return self
     */
    public function setAMQP(AMQPInterface $mqc = null)
    {
        if ($mqc) {
            $this->mqc = $mqc;

        } else {
            try {
                // if the rabbit cannot be contacted, this will throw an exception
                $this->mqc = MQConnector::getInstance();

            } catch (AMQPExceptionInterface $e) {
                // we're ok with suppressing this: it will be dealt with appropriately in the handler factory
                $this->mqc = null;
            }
        }

        return $this;
    }


    /**
     * @param Swift_Transport $swift
     * @return self
     */
    public function setSwiftTransport(Swift_Transport $swift = null)
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


    /**
     * @param string $instanceId
     * @return $this
     */
    public function setInstanceId($instanceId)
    {
        $this->instanceId = $instanceId;
        return $this;
    }

}
