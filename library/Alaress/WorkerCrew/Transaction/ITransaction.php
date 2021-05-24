<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Transaction;

interface ITransaction
{
    /**
     * Set data in this transaction
     *
     * @param string|int $key
     * @param mixed      $value
     * @return $this
     */
    public function put($key, $value);

    /**
     * Get the number of elements currently stashed inside this transaction
     *
     * @return int
     */
    public function getCount();

    /**
     * Forcibly flush the transaction, regardless of state
     *
     * @return $this
     */
    public function flush();
}
