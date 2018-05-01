<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Realtime\MessageList;

use BadMethodCallException;
use Predis\ClientInterface;

/**
 * A user-independent list interface, usable only for retracting messages.
 */
class Retractor extends AbstractList
{
    /**
     * @param ClientInterface $redis
     * @param string          $instanceId
     */
    public function __construct(ClientInterface $redis, $instanceId)
    {
        parent::__construct($redis, $instanceId, 0);
    }


    /**
     * The retractor queue is user-independent, therefore it has no access to
     * any user's set queue.
     *
     * @throws BadMethodCallException
     */
    protected function getSetKeyPattern()
    {
        throw new BadMethodCallException('Retractor queue has no access to set');
    }


    /**
     * As the retractor queue has no access to any set queue, the suffix is not used.
     *
     * @throws BadMethodCallException
     */
    protected function getSuffix()
    {
        throw new BadMethodCallException('Retractor does not support suffix');
    }
}
