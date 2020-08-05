<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Transform;

use Alaress\Json\Serializable;
use Alaress\Json\SerializeTrait;

/**
 * Class Model
 * @package Schoolbox\Resource\Storage\Transform
 */
class Model implements Serializable
{
    use SerializeTrait;

    /**
     * @var string
     */
    private $hash;

    /**
     * @var string
     */
    private $transform;

    /**
     * @var integer
     */
    private $size;

    /**
     * @var string
     */
    private $presignedUrl;

    /**
     * @param string $hash
     *
     * @return $this
     */
    public function setHash(string $hash): self
    {
        $this->hash = $hash;

        return $this;
    }

    /**
     * @param string $transform
     *
     * @return $this
     */
    public function setTransform(string $transform): self
    {
        $this->transform = $transform;

        return $this;
    }

    /**
     * @param int $size
     *
     * @return $this
     */
    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
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
    public function getTransform(): string
    {
        return $this->transform;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @param string $presignedUrl
     *
     * @return $this
     */
    public function setPresignedUrl(string $presignedUrl): self
    {
        $this->presignedUrl = $presignedUrl;

        return $this;
    }

    /**
     * @return string
     */
    public function getPresignedUrl(): string
    {
        return $this->presignedUrl;
    }

    /**
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        return ['transform', 'presignedUrl'];
    }
}
