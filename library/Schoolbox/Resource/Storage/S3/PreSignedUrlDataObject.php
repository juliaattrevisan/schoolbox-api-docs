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
    private $hash;

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
     * @var string
     */
    private $itemPath;

    /**
     * @var string|null
     */
    private $size;

    /**
     * PreSignedUrlDataObject constructor.
     *
     * @param string      $hash
     * @param string      $url
     * @param string      $cacheControl
     * @param string      $expires
     * @param string      $itemPath
     * @param string|null $size
     */
    public function __construct(string $hash, string $url, string $cacheControl, string $expires, string $itemPath, string $size = null)
    {
        $this->hash = $hash;
        $this->url = $url;
        $this->expires = $expires;
        $this->cacheControl = $cacheControl;
        $this->itemPath = $itemPath;
        $this->size = $size;
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return $this->hash;
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

    /**
     * @return string
     */
    public function getItemPath(): string
    {
        return $this->itemPath;
    }

    /**
     * @return string|null
     */
    public function getSize(): ?string
    {
        return $this->size;
    }
}
