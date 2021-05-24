<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment;

/**
 * Implementations of this interface are able to consistently sort assessments for
 * different use-case throughout the system.
 */
interface IAssessableComparator
{

    /**
     * Returns:
     * * $left <  $right => -ve
     * * $left == $right => 0
     * * $left >  $right => +ve
     *
     * @param IAssessable $left
     * @param IAssessable $right
     * @return int
     */
    public function compare(IAssessable $left, IAssessable $right): int;


    /**
     * Return a this comparator as a callable, able to be passed to eg Collection->sortByCallable()
     *
     * @return callable
     */
    public function getCallable(): callable;
}
