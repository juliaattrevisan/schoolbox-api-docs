<?php

/**
 * Metadata
 *
 * A container for various metadata about each article such as its comment count.
 *
 * In future this should be the place where we put other details like number of opens/views,
 * number of reactions, etc.
 *
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News;

class Metadata
{
    /**
     * @var int
     */
    private $commentCount = 0;

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
