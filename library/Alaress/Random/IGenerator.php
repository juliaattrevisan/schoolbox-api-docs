<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Random;

/**
 * Interface IFloatGenerator
 *
 * @package Alaress\Random
 */
interface IGenerator
{
    /**
     * Returns a random float between 0 and 1.
     *
     * @return float
     */
    public function random();
}
