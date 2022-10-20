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
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender;

class Connector extends AMQPConnector
{
    const PRIMARY_EXCHANGE = 'sb.%s.tx';
    const PRIMARY_TYPE     = 'topic';

    /**
     * AMQP connection options
     */
    public const AMQP_CONNECTION_OPTION_HOST      = 'host';
    public const AMQP_CONNECTION_OPTION_PORT      = 'port';
    public const AMQP_CONNECTION_OPTION_USER      = 'user';
    public const AMQP_CONNECTION_OPTION_PASSWORD  = 'password';
    public const AMQP_CONNECTION_OPTION_VHOST     = 'vhost';
    public const AMQP_CONNECTION_OPTION_HEARTBEAT = 'heartbeat';

    /**
     * Constructor
     *
     * @param array $settings
     * @param array $connectionOptions
     */
    public function __construct(
        array $settings = [],
        array $connectionOptions = []
    ) {
        parent::__construct($settings);
        $this->connect($connectionOptions);
    }


    /**
     * Establishes a new AMQP connection if it doesn't already exist.
     * @return void
     */
    protected function connect(array $connectionOptions)
    {
        if (!$this->conn instanceof AMQPStreamConnection) {
            $this->conn = AMQPStreamConnection::create_connection(
                [
                    [
                        self::AMQP_CONNECTION_OPTION_HOST     => $connectionOptions[self::AMQP_CONNECTION_OPTION_HOST],
                        self::AMQP_CONNECTION_OPTION_PORT     => $connectionOptions[self::AMQP_CONNECTION_OPTION_PORT],
                        self::AMQP_CONNECTION_OPTION_USER     => $connectionOptions[self::AMQP_CONNECTION_OPTION_USER],
                        self::AMQP_CONNECTION_OPTION_PASSWORD => $connectionOptions[self::AMQP_CONNECTION_OPTION_PASSWORD],
                        self::AMQP_CONNECTION_OPTION_VHOST    => $connectionOptions[self::AMQP_CONNECTION_OPTION_VHOST],
                    ],
                ],
                $connectionOptions,
            );

            if (isset($connectionOptions[self::AMQP_CONNECTION_OPTION_HEARTBEAT])) {
                $heartbeat = new PCNTLHeartbeatSender($this->conn);
                $heartbeat->register();
            }
        }
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
