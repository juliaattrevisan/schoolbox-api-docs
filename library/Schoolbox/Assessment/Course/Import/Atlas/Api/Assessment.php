<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use JsonSerializable;

/**
 * Object representation of an assessment from Atlas Rubicon.
 */
class Assessment implements JsonSerializable
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var null|string
     */
    private $title;

    /**
     * @var null|string
     */
    private $type;

    /**
     * @var null|string
     */
    private $method;

    /**
     * @var null|string
     */
    private $content;

    /**
     * @var Standard[]
     */
    private $standards = [];

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId(int $id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param null|string $title
     * @return static
     */
    public function setTitle(?string $title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @param null|string $type
     * @return static
     */
    public function setType(?string $type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * @param null|string $method
     * @return static
     */
    public function setMethod(?string $method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * @param null|string $content
     * @return static
     */
    public function setContent(?string $content)
    {
        $this->content = $content;
        return $this;
    }

    /**
     * @return Standard[]
     */
    public function getStandards(): array
    {
        return $this->standards;
    }

    /**
     * @param Standard[] $standards
     * @return static
     */
    public function setStandards(array $standards)
    {
        $this->standards = $standards;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return [
            'id'        => $this->id,
            'title'     => $this->title,
            'type'      => $this->type,
            'method'    => $this->method,
            'content'   => $this->content,
            'standards' => $this->standards,
        ];
    }
}
