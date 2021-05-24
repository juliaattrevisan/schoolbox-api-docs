<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Validation;

use Schoolbox\Comms\News\Audience;
use Alaress\Specification;

/**
 * Ensures audience segment trees are within the valid height
 */
class ValidTreeHeight extends Specification\Leaf
{

    /** @var int */
    private $maxHeight;


    /**
     * @param int $maxHeight
     */
    public function __construct(int $maxHeight)
    {
        $this->maxHeight = $maxHeight;
    }


    /**
     * @param Audience\Model $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        return $this->getTreeHeight($candidate) <= $this->maxHeight;
    }


    /**
     * Returns the height of the tree containing the segment
     *
     * @param Audience\Model $segment
     * @return int
     */
    private function getTreeHeight(Audience\Model $segment): int
    {
        // Find the parent
        $parent = $segment;
        while ($parent->getParent() instanceof Audience\Model) {
            $parent = $parent->getParent();
        }

        return $this->getSegmentHeightRecursive($parent);
    }


    /**
     * Returns the height of a segment/node in a tree
     *
     * @param Audience\Model $segment
     * @return int
     */
    private function getSegmentHeightRecursive(Audience\Model $segment): int
    {
        $maxHeight = 0;
        foreach ($segment->getChildren() as $childSegment) {
            $maxHeight = \max($maxHeight, 1 + $this->getSegmentHeightRecursive($childSegment));
        }
        return $maxHeight;
    }
}
