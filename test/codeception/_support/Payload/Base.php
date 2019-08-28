<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Payload;

/**
 * An undefined/anonymous base request.
 *
 * Can be used to avoid the overhead of defining an explicit class to represent a request.
 */
class Base extends Request
{
    /** @var string */
    private $method;

    /** @var string */
    private $url;

    /**
     * @param string $method
     * @param string $referrer
     * @param string $url
     */
    public function __construct(string $method, string $referrer, string $url)
    {
        $this->setReferer($referrer);

        $this->method = $method;
        $this->url    = $url;
    }

    /**
     * @inheritDoc */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @inheritDoc */
    protected function getUrl(): string
    {
        return $this->url;
    }
}
