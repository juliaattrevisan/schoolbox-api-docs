<?php

/**
 * Base interface for objects which are comparable to each other.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
interface IComparable
{
    /**
     * Compares two objects, ideally of the same type.
     *
     * @param IComparable $other
     *
     * @return int -1 if $this < $other, 1 if $this > $other, 0 if $this = $other
     */
    public function compare($other);
}
