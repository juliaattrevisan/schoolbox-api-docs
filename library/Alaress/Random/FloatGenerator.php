<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Random;

/**
 * Generator for a pseudo-random float between 0 and 1.
 */
class FloatGenerator implements IGenerator
{
    /**
     * Constructor
     *
     * @param int $seed If provided, use this number to seed the RNG
     */
    public function __construct($seed = null)
    {
        if (isset($seed)) {
            mt_srand($seed);
        }
    }

    /**
     * @return float
     */
    public function generate()
    {
        $randmax = mt_getrandmax();
        return mt_rand(0, $randmax) / $randmax;
    }
}
