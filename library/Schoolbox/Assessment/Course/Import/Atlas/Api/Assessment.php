<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

/**
 * Object representation of an assessment from Atlas Rubicon.
 */
class Assessment
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $content;

    /**
     * @var Standard[]
     */
    private $standards;

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
     * @return string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return static
     */
    public function setTitle(?string $title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return static
     */
    public function setType(?string $type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * @param string $method
     * @return static
     */
    public function setMethod(?string $method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @return string
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * @param string $content
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
}
