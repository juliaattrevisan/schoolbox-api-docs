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
     * @var \Schoolbox\Resource\Storage\Model|null
     */
    private $storage;

    /**
     * @var string
     */
    private $transform;

    /**
     * @var integer
     */
    private $size;

    /**
     * @param \Schoolbox\Resource\Storage\Model|null $storage
     *
     * @return Model
     */
    public function setStorage(?\Schoolbox\Resource\Storage\Model $storage): self
    {
        $this->storage = $storage;

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
     * @return \Schoolbox\Resource\Storage\Model|null
     */
    public function getStorage(): ?\Schoolbox\Resource\Storage\Model
    {
        return $this->storage;
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
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        return ['transform'];
    }
}
