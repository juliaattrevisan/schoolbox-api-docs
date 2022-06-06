<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Password;

/**
 * Password hash abstraction class.
 *
 * @package Alaress\Password
 */
class Service
{
    /**
     * The hashing algorithm to use.
     *
     * This is one of the `PASSWORD_*` constants.
     *
     * @var string
     */
    protected $algorithm;

    /**
     * @var int
     */
    protected $cost;

    /**
     * Constructor
     *
     * @param string $algorithm One of the PASSWORD_* constants
     * @param int    $cost      The algorithmic cost to use
     */
    public function __construct($algorithm = PASSWORD_DEFAULT, $cost = 10)
    {
        $this->algorithm = $algorithm;
        $this->cost      = $cost;
    }

    /**
     * Hashes password.
     *
     * @param  string $password
     * @return bool|string
     */
    public function hash($password)
    {
        return password_hash($password, $this->algorithm, ['cost' => $this->cost]);
    }

    /**
     * Verifies that $password matches $hash (in a timing-safe manner)
     *
     * @param  string $password
     * @param  string $hash
     * @return bool
     */
    public function verify($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Does this hash need to be regenerated?
     * If the cost is different to the cost the hash was generated with,
     * it needs to be regenerated.
     *
     * @param  string $hash
     * @return string
     */
    public function needsRehash($hash)
    {
        return password_needs_rehash($hash, $this->algorithm, ['cost' => $this->cost]);
    }
}
