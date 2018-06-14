<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
     * @var string
     */
    private $rawText;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $code;

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
    public function getCode(): ?string
    {
        return $this->code;
    }

    /**
     * @param string $code
     * @return static
     */
    public function setCode(?string $code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return string
     */
    public function getRawText(): ?string
    {
        return $this->rawText;
    }

    /**
     * @param string $rawText
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
