<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Random;

/**
 * Generator for a pseudo-random number.
 */
class Generator implements IGenerator
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
    public function random()
    {
        $randmax = mt_getrandmax();
        return mt_rand(0, $randmax) / $randmax;
    }
}
