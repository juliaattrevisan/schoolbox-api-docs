<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Storage;

/**
 * Hash generator for storage files.
 */
class HashGenerator
{
    /**
     * Hash algorithm to use.
     *
     * @var string
     */
    private $algo;

    /**
     * Constructor
     *
     * @param string $algo
     */
    public function __construct($algo = 'sha1')
    {
        $this->algo = $algo;
    }

    /**
     * Generate a hash for a file stream.
     *
     * @param resource $fp
     * @return string
     */
    public function generateHash($fp): string
    {
        $hp = hash_init($this->algo);
        hash_update_stream($hp, $fp);
        return hash_final($hp);
    }
}
