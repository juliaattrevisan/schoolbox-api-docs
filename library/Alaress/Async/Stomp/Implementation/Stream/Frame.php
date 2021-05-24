<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\Stream;

use Alaress\Async\Stomp\IFrame;
use Stomp\Transport\Frame as BaseFrame;

/**
 * Adapter for frames that are retrieved via the STOMP protocol, when accessed
 * via the StompPHP library.
 */
class Frame implements IFrame
{
    /**
     * @var BaseFrame
     */
    private $frame;


    /**
     * Constructor
     *
     * @param BaseFrame $frame
     */
    public function __construct(BaseFrame $frame)
    {
        $this->frame = $frame;
    }


    /**
     * @inheritDoc
     */
    public function getBody(): string
    {
        return $this->frame->body;
    }


    /**
     * @inheritDoc
     */
    public function getHeader(string $headerName)
    {
        return $this->frame[$headerName] ?? null;
    }
}
