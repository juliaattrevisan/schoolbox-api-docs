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
    /**
     * @param ClientInterface $redis
     * @param string          $instanceId
     * @param string          $identifier
     */
    public function __construct(ClientInterface $redis, string $instanceId, private string $identifier)
    {
        // EventObjectList doesn't use userId, passing 0
        parent::__construct($redis, $instanceId, 0);
    }

    /**
     * @inheritdoc
     */
    protected function getSuffix(): string
    {
        return 'object';
    }

    /**
     * Deletes all the members of the current event object set
     * and remove the set itself.
     */
    public function retract(): void
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
                    $this->redis->executeCommand(new RawCommand('UNLINK', [$key]));
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
                $this->redis->executeCommand(new RawCommand('UNLINK', [$this->getRedisSetKey()]));
            } catch (ServerException $e) {
                $this->redis->del([$this->getRedisSetKey()]);
            }
        } else {
            $this->redis->del([$this->getRedisSetKey()]);
        }
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return $this->identifier;
    }
}
