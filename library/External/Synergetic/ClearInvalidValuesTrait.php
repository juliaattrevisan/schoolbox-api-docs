<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

/**
 * Trait for clearing invalid values out of Synergetic
 */
trait External_Synergetic_ClearInvalidValuesTrait
{
    /**
     * This is a helper function to clear the 'Not Selected' placeholder value
     * that Synergetic loves to add as an option in every lookup table
     *
     * @param array $array
     * @param string $placeholder
     * @return array
     */
    protected function clearInvalidValues($array, $placeholder = 'Not Selected')
    {
        // Remove the 'Not Selected' Synergetic placeholder value

        return array_map(function ($value) use ($placeholder) {
            if (is_array($value)) {
                return $this->clearInvalidValues($value);
            } else {
                return trim(str_replace($placeholder, '', $value));
            }
        }, $array);
    }
}
