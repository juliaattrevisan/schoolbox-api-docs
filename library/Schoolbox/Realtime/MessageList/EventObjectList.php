<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList;

use Predis\ClientInterface;
use Predis\Command\RawCommand;
use Predis\Response\ServerException;

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
        $useUnlink = true;
        foreach ($idList as $key) {
            if ($useUnlink) {
                try {
                    $this->redis->executeCommand(new RawCommand(['UNLINK', $key]));
                } catch (ServerException $e) {
                    $useUnlink = false;
                    $this->redis->del([$key]);
                }
            } else {
                $this->redis->del([$key]);
            }
        }

        // delete this set
        if ($useUnlink) {
            try {
                $this->redis->executeCommand(new RawCommand(['UNLINK', $this->getRedisSetKey()]));
            } catch (ServerException $e) {
                $this->redis->del([$this->getRedisSetKey()]);
            }
        } else {
            $this->redis->del([$this->getRedisSetKey()]);
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
