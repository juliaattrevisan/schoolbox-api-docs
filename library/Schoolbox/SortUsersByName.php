<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox;

/**
 * Comparator for sorting users by firstname, then lastname
 */
class SortUsersByName implements IUserComparator
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
    public function compare(User $left, User $right): int
    {
        $callable = $this->getCallable();
        return $callable($left, $right);
    }


    /**
     * Compares users by lastname, then firstname
     *
     * @return callable
     */
    public function getCallable(): callable
    {
        return function (User $left, User $right) {
            $cmp = \strcasecmp($left->getLastname() ?? '', $right->getLastname() ?? '');
            if ($cmp === 0) {
                $cmp = \strcasecmp($left->getFirstname() ?? '', $right->getFirstname() ?? '');
            }
            return $cmp;
        };
    }
}
