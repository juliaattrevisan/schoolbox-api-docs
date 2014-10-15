<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Alaress\Async\Queue;


use Alaress\Async\AMQP\AMQPInterface;
use Alaress\Async\Notification\Instance\Instance;
use Exception;
use InvalidArgumentException;
use PhpAmqpLib\Exception\AMQPRuntimeException;

class AMQPQueue implements QueueInterface
{

    /** @var AMQPInterface */
    protected $conn;

    /** @var string */
    protected $name;

    /** @var string */
    protected $rkey;


    /**
     * @param AMQPInterface $connector   the connector to use for AMQP transactions
     * @param string        $name        the unique name to be used for this queue in AMQP
     * @param string        $routingKey  the routing key used to bind this queue to its parent exchange
     * @throws AMQPRuntimeException
     */
    public function __construct(AMQPInterface $connector, $name, $routingKey)
    {
        $this->rkey = $routingKey;
        $this->name = INSTANCE_ID . '.u.' . $name;

        // ensure it exists
        $this->conn = $connector;
        $this->conn->declareQueue($this->name);
    }


    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * @return string
     */
    public function getRoutingKey()
    {
        return $this->rkey;
    }


    /**
     * @throws Exception
     * @return Instance[]
     */
    public function fetch()
    {
        $instances = array();
        $nacks     = array();

        try {
            while ($m = $this->conn->readFromQueue($this->name)) {
                $nacks[] = $m;

                // unwrap the formatting
                $body = json_decode($m->body, true);

                // attempt to unserialize the message
                $instances[] = Instance::jsonUnserialize($body['message']);
            }
        } catch (Exception $e) {
            // regardless of what happens above, it's imperative that we reject the messages we pulled
            $this->conn->rejectMessages($nacks);
            // *now* you can freak out
            throw $e;
        }

        $this->conn->rejectMessages($nacks);

        return $instances;
    }


    /**
     * Returns whether or not this queue contains the given instance
     *
     * @param Instance $instance
     * @throws InvalidArgumentException
     * @return bool
     */
    public function contains(Instance $instance)
    {
        $result = false;
        $nack   = array();
        $q      = $this->getName();

        try {
            while ($m = $this->conn->readFromQueue($q)) {
                // store list of retrieved messages to NACK later
                $nack[] = $m;
                $unjson = json_decode($m->body, true);

                $compare = Instance::jsonUnserialize($unjson['message']);

                if ($instance->getBaseHash() == $compare->getBaseHash()) {
                    if ($instance->getParamHash() == $compare->getParamHash()) {
                        $result = true;
                        break;
                    }
                }
            }
        } catch (InvalidArgumentException $e) {
            // regardless of anything else, you MUST reject the messages you retrieved
            $this->conn->rejectMessages($nack);
            throw $e;
        }

        // push any messages we retrieved back onto the queue
        $this->conn->rejectMessages($nack);

        return $result;
    }


    /**
     * Request to drain the queue of its contents.
     *
     * @return void
     */
    public function drain()
    {
        $ack = array();
        while ($m = $this->conn->readFromQueue($this->getName())) {
            $ack[] = $m;
        }
        $this->conn->acceptMessages($ack);
    }

}