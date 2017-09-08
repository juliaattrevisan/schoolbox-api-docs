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
     * Generates a random value.
     * The type of random value is determined by the generator.
     *
     * @return mixed
     */
    public function generate();
}
