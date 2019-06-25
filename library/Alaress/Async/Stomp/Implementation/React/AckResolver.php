<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\React;

use Alaress\Async\Stomp\IAckResolver;
use React\Stomp\AckResolver as BaseAckResolver;

/**
 * Adapter for the acknowledgement resolver for frames retrieved via the STOMP
 * protocol, when accessed via the React\Stomp library.
 */
class AckResolver implements IAckResolver
{
    /**
     * @var BaseAckResolver
     */
    private $ackResolver;


    /**
     * Constructor
     *
     * @param BaseAckResolver $ackResolver
     */
    public function __construct(BaseAckResolver $ackResolver)
    {
        $this->ackResolver = $ackResolver;
    }


    /**
     * @inheritDoc
     */
    public function ack(array $headers = []): void
    {
        $this->ackResolver->ack($headers);
    }


    /**
     * @inheritDoc
     */
    public function nack(array $headers = []): void
    {
        $this->ackResolver->nack($headers);
    }
}
