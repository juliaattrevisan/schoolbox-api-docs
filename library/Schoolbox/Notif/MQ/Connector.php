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
use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\Notification\Instance\Instance;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\IConfigLoader;

class Connector extends AMQPConnector
{

    /** @var AMQPInterface */
    protected static $instance;

    /**
     * @return Connector
     */
    public static function getInstance()
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }


    const PRIMARY_EXCHANGE = 'sb.%s.tx';
    const PRIMARY_TYPE     = 'topic';

    /** @var IConfigLoader */
    protected $conf;


    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        parent::__construct($settings);
        $this->conf = ConfigLoader::getInstance();
        $this->connect();
    }


    /**
     * Establishes a new AMQP connection if it doesn't already exist.
     * @return void
     */
    protected function connect()
    {
        if (!$this->conn instanceof AMQPStreamConnection) {
            $this->conn = new AMQPStreamConnection(
                $this->conf->get('amqp_host'),
                $this->conf->get('amqp_port'),
                $this->conf->get('amqp_user'),
                $this->conf->get('amqp_pass'),
                $this->conf->get('amqp_vhost')
            );
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
