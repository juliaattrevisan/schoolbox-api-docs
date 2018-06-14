<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import\Atlas\Api;

/**
 * Object representation of an standard from Atlas Rubicon.
 */
class Standard
{
    /**
     * @var string
     */
    private $rawText;

    /**
     * @return string
     */
    public function getRawText(): string
    {
        return $this->rawText;
    }

    /**
     * @param string $rawText
     * @return static
     */
    public function setRawText(string $rawText)
    {
        $this->rawText = $rawText;
        return $this;
    }
}
