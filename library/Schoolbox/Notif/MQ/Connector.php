<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Notif\MQ;

use Alaress\Async\AMQP\AMQPConnector;
use Alaress\Async\AMQP\AMQPExchange;
use Alaress\Async\Notification\Instance\Instance;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;
use PhpAmqpLib\Connection\AMQPConnectionFactory;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender;

/**
 * AMQP connection, specific to the needs of the schedule and route daemons.
 */
class Connector extends AMQPConnector
{
    const PRIMARY_EXCHANGE = 'sb.%s.tx';
    const PRIMARY_TYPE     = 'topic';

    /**
     * AMQP connection options
     */
    public const AMQP_CONNECTION_OPTION_HOST          = 'host';
    public const AMQP_CONNECTION_OPTION_PORT          = 'port';
    public const AMQP_CONNECTION_OPTION_USER          = 'user';
    public const AMQP_CONNECTION_OPTION_PASSWORD      = 'password';
    public const AMQP_CONNECTION_OPTION_VHOST         = 'vhost';
    public const AMQP_CONNECTION_OPTION_HEARTBEAT     = 'heartbeat';
    public const AMQP_CONNECTION_OPTION_READ_TIMEOUT  = 'read_timeout';
    public const AMQP_CONNECTION_OPTION_WRITE_TIMEOUT = 'write_timeout';
    public const AMQP_CONNECTION_OPTION_KEEPALIVE     = 'keepalive';

    /**
     * @var AbstractConnection|null
     */
    private ?AbstractConnection $conn = null;

    /**
     * Constructor
     *
     * @param array $settings
     * @param array $connectionOptions
     */
    public function __construct(
        array $settings = [],
        private array $connectionOptions = [],
    ) {
        parent::__construct($settings);
        AbstractConnection::validate_host($connectionOptions);
    }


    /**
     * Creates an `AMQPStreamConnection` instance if not connected already.
     *
     * If the `AMQP_CONNECTION_OPTION_HEARTBEAT` option is set: register a
     * heartbeat connection.
     *
     * @return AbstractConnection
     */
    protected function getConnection(): AbstractConnection
    {
        // If the connection is disconnected:
        // just close the connection and recreate.
        if ($this->conn && !$this->conn->isConnected()) {
            $this->closeConnection();
            $this->conn = null;
        }

        // Connection doesn't exist: create it
        if (!$this->conn) {
            $connectionConfig = new AMQPConnectionConfig();
            $connectionConfig->setIoType(AMQPConnectionConfig::IO_TYPE_STREAM);
            $connectionConfig->setHost($this->connectionOptions[self::AMQP_CONNECTION_OPTION_HOST]);
            $connectionConfig->setPort($this->connectionOptions[self::AMQP_CONNECTION_OPTION_PORT]);
            $connectionConfig->setUser($this->connectionOptions[self::AMQP_CONNECTION_OPTION_USER]);
            $connectionConfig->setPassword($this->connectionOptions[self::AMQP_CONNECTION_OPTION_PASSWORD]);
            $connectionConfig->setVhost($this->connectionOptions[self::AMQP_CONNECTION_OPTION_VHOST]);
            if (isset($this->connectionOptions[self::AMQP_CONNECTION_OPTION_HEARTBEAT])) {
                $connectionConfig->setHeartbeat($this->connectionOptions[self::AMQP_CONNECTION_OPTION_HEARTBEAT]);
            }
            if (isset($this->connectionOptions[self::AMQP_CONNECTION_OPTION_READ_TIMEOUT])) {
                $connectionConfig->setReadTimeout($this->connectionOptions[self::AMQP_CONNECTION_OPTION_READ_TIMEOUT]);
            }
            if (isset($this->connectionOptions[self::AMQP_CONNECTION_OPTION_WRITE_TIMEOUT])) {
                $connectionConfig->setWriteTimeout($this->connectionOptions[self::AMQP_CONNECTION_OPTION_WRITE_TIMEOUT]);
            }
            if (isset($this->connectionOptions[self::AMQP_CONNECTION_OPTION_KEEPALIVE])) {
                $connectionConfig->setKeepalive((bool)$this->connectionOptions[self::AMQP_CONNECTION_OPTION_KEEPALIVE]);
            }

            $this->conn = AMQPConnectionFactory::create($connectionConfig);
            if (isset($this->connectionOptions[self::AMQP_CONNECTION_OPTION_HEARTBEAT])) {
                $heartbeat = new PCNTLHeartbeatSender($this->conn);
                $heartbeat->register();
            }
        }
        return $this->conn;
    }


    /**
     * @inheritdoc
     */
    public function closeConnection(): self
    {
        if ($this->conn) {
            try {
                $this->conn->close();
            } catch (\Exception) {
                // ignore errors raised when attempting to close the connection
            }
            $this->conn = null;
        }
        return $this;
    }


    /**
     * @return AMQPExchange
     */
    public function getPrimaryExchange()
    {
        return new AMQPExchange(sprintf(self::PRIMARY_EXCHANGE, INSTANCE_ID), self::PRIMARY_TYPE);
    }


    /**
     * Accepts a string representation of a message and calculates a hash for it
     *
     * @param string $message
     * @param string $channelId
     * @return string
     */
    protected function hashMessage($message, $channelId)
    {
        $instance = null;

        // attempt to extract the message body, so as to efficiently de-dupe resent messages
        if ($data = json_decode($message, true)) {
            if (array_key_exists('message', $data)) {
                $instance = Instance::jsonUnserialize($data['message']);
            }

            if ($instance instanceof Instance) {
                return $instance->getBaseHash();
            }
        }

        // couldn't decode, so let's just hash what we got
        $bits   = [is_null($channelId) ? 0 : $channelId];
        $bits[] = $message;
        return sha1(implode('.', $bits));
    }
}
