<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Stream;

use Alaress\Async\Stomp\IAckResolver;
use React\Stream\DuplexResourceStream as Stream;
use Stomp\Protocol\Protocol;
use Stomp\Transport\Frame as BaseFrame;

/**
 * Adapter for the acknowledgement resolver for frames retrieved via the STOMP
 * protocol, when accessed via the StompPHP library.
 */
class AckResolver implements IAckResolver
{
    /**
     * @var Stream
     */
    private $stream;


    /**
     * @var Protocol
     */
    private $protocol;


    /**
     * @var BaseFrame
     */
    private $frame;


    /**
     * Constructor
     *
     * @param Stream    $stream
     * @param Protocol  $protocol
     * @param BaseFrame $frame
     */
    public function __construct(Stream $stream, Protocol $protocol, BaseFrame $frame)
    {
        $this->stream   = $stream;
        $this->protocol = $protocol;
        $this->frame    = $frame;
    }


    /**
     * @inheritDoc
     */
    public function ack(array $headers = []): void
    {
        $this->stream->write($this->protocol->getAckFrame($this->frame));
    }


    /**
     * @inheritDoc
     */
    public function nack(array $headers = []): void
    {
        $this->stream->write($this->protocol->getNackFrame($this->frame));
    }
}
