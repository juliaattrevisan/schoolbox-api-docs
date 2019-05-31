<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      https://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

class Metadata
{
    /**
     * @var int
     */
    public $commentCount = 0;

    /**
     * Metadata constructor.
     */
    public function __construct()
    {
        // Nothing here for now...
    }

    /**
     * @param int $commentCount
     *
     * @return Metadata
     */
    public function setCommentCount(int $commentCount): self
    {
        $this->commentCount = $commentCount;

        return $this;
    }

    /**
     * @return int
     */
    public function getCommentCount(): int
    {
        return $this->commentCount;
    }
}
