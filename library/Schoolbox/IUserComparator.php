<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox;

/**
 * Implementations of this interface are able to consistently sort users for
 * different use-case throughout the system.
 */
interface IUserComparator
{

    /**
     * Returns:
     * * $left <  $right => -ve
     * * $left == $right => 0
     * * $left >  $right => +ve
     *
     * @param User $left
     * @param User $right
     * @return int
     */
    public function compare(User $left, User $right): int;


    /**
     * Return a this comparator as a callable, able to be passed to eg Collection->sortByCallable()
     *
     * @return callable
     */
    public function getCallable(): callable;
}
