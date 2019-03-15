<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList;

use Alaress\Async\Notification\Instance\Instance;
use Predis\ClientInterface;
use Predis\Command\RawCommand;

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
        $this->name = $identifier;
    }

    /**
     * @inheritdoc
     */
    protected function getSuffix()
    {
        return 'object';
    }

    /**
     * Deletes all the members of the current event object set
     * and remove the set itself.
     */
    public function retract()
    {
        // get all members of the current set
        $idList = $this->redis->smembers($this->getRedisSetKey());
        if (empty($idList)) {
            return;
        }

        // delete one by one
        $count = count($idList);
        for ($i = 0; $i < $count; ++$i) {
            $key  = $idList[$i];
            $unlinkArgs[] = $key;
            $this->redis->executeCommand(new RawCommand(['UNLINK', $key]));
        }

        // make sure that everything has been removed
        $idList = $this->redis->smembers($this->getRedisSetKey());

        if (empty($idList)) {
            $this->redis->executeCommand(new RawCommand(['UNLINK', $this->getRedisSetKey()]));
        }
        return;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->name;
    }

}