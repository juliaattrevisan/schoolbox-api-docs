<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Password;

use SensitiveParameter;

/**
 * Password hash abstraction class.
 *
 * @package Alaress\Password
 */
class Service
{
    /**
     * Constructor
     *
     * @param string $algorithm The hashing algorithm to use: one of the PASSWORD_* constants
     * @param int    $cost      The algorithmic cost to use
     */
    public function __construct(private string $algorithm = PASSWORD_DEFAULT, private int $cost = 10)
    {
    }

    /**
     * Hashes password.
     *
     * @param string $password
     * @return string
     */
    public function hash(#[SensitiveParameter] string $password): string
    {
        return password_hash($password, $this->algorithm, ['cost' => $this->cost]);
    }

    /**
     * Verifies that $password matches $hash (in a timing-safe manner)
     *
     * @param string $password
     * @param string $hash
     * @return bool
     */
    public function verify(#[SensitiveParameter] string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Does this hash need to be regenerated?
     * If the cost is different to the cost the hash was generated with,
     * it needs to be regenerated.
     *
     * @param  string $hash
     * @return bool
     */
    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm, ['cost' => $this->cost]);
    }
}
