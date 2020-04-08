<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\S3;

class PreSignedUrlDataObject
{
    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $expires;

    /**
     * @var string
     */
    private $cacheControl;

    /**
     * PreSignedUrlDataObject constructor.
     *
     * @param string $url
     * @param string $cacheControl
     * @param string $expires
     */
    public function __construct(string $url, string $cacheControl, string $expires)
    {
        $this->url          = $url;
        $this->expires      = $expires;
        $this->cacheControl = $cacheControl;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getCacheControl(): string
    {
        return $this->cacheControl;
    }

    /**
     * @return string
     */
    public function getExpires(): string
    {
        return $this->expires;
    }
}
