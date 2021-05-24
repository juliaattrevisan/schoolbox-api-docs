<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

use JsonSerializable;

/**
 * Object representation of an standard from Atlas Rubicon.
 */
class Standard implements JsonSerializable
{
    /**
     * @var null|string
     */
    private $rawText;

    /**
     * @var null|string
     */
    private $title;

    /**
     * @var null|string
     */
    private $code;

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
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @param null|string $code
     * @return static
     */
    public function setCode(?string $code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getRawText(): ?string
    {
        return $this->rawText;
    }

    /**
     * @param null|string $rawText
     * @return static
     */
    public function setRawText(?string $rawText)
    {
        $this->rawText = $rawText;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize()
    {
        return [
            'title' => $this->title,
            'code'  => $this->code,
        ];
    }
}
