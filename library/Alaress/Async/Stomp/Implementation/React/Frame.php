<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp\Implementation\React;

use Alaress\Async\Stomp\IFrame;
use React\Stomp\Protocol\Frame as BaseFrame;

/**
 * Class Frame
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
        return $this->frame->getHeader($headerName);
    }
}
