<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Enqueue;

use Alaress\Async\Stomp\IFrame;
use Interop\Queue\Message;

/**
 * Adapter for frames that are retrieved via the Enqueue library.
 */
class Frame implements IFrame
{
    /**
     * Interop calls frames messages...
     *
     * @var Message
     */
    private $message;


    /**
     * Constructor
     *
     * @param Message $message
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }


    /**
     * @inheritDoc
     */
    public function getBody(): string
    {
        return $this->message->getBody();
    }


    /**
     * @inheritDoc
     */
    public function getHeader(string $headerName)
    {
        return $this->message->getHeader($headerName);
    }
}
