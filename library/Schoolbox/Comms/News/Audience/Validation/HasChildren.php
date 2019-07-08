<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience\Validation;

use Schoolbox\Comms\News\Audience;
use Alaress\Specification;

/**
 * Ensures an audience segment has children
 */
class HasChildren extends Specification\Leaf
{

    /**
     * @param Audience\Model $candidate
     * @return bool
     */
    public function isSatisfiedBy($candidate): bool
    {
        return (bool)$candidate->getChildren()->count();
    }
}
