<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\StompPhp;

use Alaress\Async\Stomp\IAckResolver;
use Stomp\StatefulStomp as BaseClient;
use Stomp\Transport\Frame as BaseFrame;

/**
 * Adapter for the acknowledgement resolver for frames retrieved via the STOMP
 * protocol, when accessed via the StompPHP library.
 */
class AckResolver implements IAckResolver
{
    /**
     * @var BaseClient
     */
    private $client;


    /**
     * @var BaseFrame
     */
    private $frame;


    /**
     * @var bool
     */
    private $isAckd;


    /**
     * Constructor
     *
     * @param BaseClient $client
     * @param BaseFrame  $frame
     */
    public function __construct(BaseClient $client, BaseFrame $frame)
    {
        $this->client = $client;
        $this->frame  = $frame;
    }


    /**
     * @inheritDoc
     */
    public function ack(array $headers = []): void
    {
        $this->client->ack($this->frame);
    }


    /**
     * @inheritDoc
     */
    public function nack(array $headers = []): void
    {
        $this->client->nack($this->frame);
    }


    /**
     * Has the frame been acknowledged?
     *
     * @return bool
     */
    public function isAcknowledged(): bool
    {
        return $this->isAckd;
    }
}
