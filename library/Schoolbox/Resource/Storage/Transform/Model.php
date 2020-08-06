<?php
/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Resource\Storage\Transform;

/**
 * Class Model
 * @package Schoolbox\Resource\Storage\Transform
 */
class Model
{
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
     * @param string $hash
     *
     * @return Model
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
    public function getHash(): ?string
    {
        return $this->hash;
    }

    /**
     * @return string
     */
    public function getTransform(): ?string
    {
        return $this->transform;
    }

    /**
     * @return int
     */
    public function getSize(): ?int
    {
        return $this->size;
    }
}
