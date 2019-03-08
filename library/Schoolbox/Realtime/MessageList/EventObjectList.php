<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Notification\Instance\Instance;
use Predis\ClientInterface;

/**
 * An event object based list interface.
 */
class EventObjectList extends AbstractList
{

    private $name;

    /**
     * @param ClientInterface $redis
     * @param string          $instanceId
     * @param int             $identifier
     */
    public function __construct(ClientInterface $redis, $instanceId, $identifier)
    {
        parent::__construct($redis, $instanceId, $identifier);

        $this->name     = $identifier;
    }

    protected function getSuffix()
    {
        return 'object';
    }

    public function retract(Instance $instance)
    {
        $idList = $this->redis->smembers($this->getRedisSetKey());


        if (empty($idList)) {
            return;
        }

        $result = $this->redis->mget($idList);

        $count = count($idList);

        for ($i = 0; $i < $count; ++$i) {
            $key  = $idList[$i];
            $this->redis->del($key);
            $this->redis->srem($this->getRedisSetKey(), $key);
        }
    }

    /**
     * Returns the name of this queue
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

}