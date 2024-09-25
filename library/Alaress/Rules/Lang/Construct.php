<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Rules\Lang;

/**
 * Interface Construct
 *
 * The common interface for all language constructs used in the Rules query "language"
 *
 * @package Alaress\Rules\Lang
 */
interface Construct
{

    /**
     * Return a summary text describing the state of this construct. Essentially __toString
     *
     * @return string
     */
    public function getSummary();


    /**
     * Returns the field this construct is operating on
     *
     * @return string
     */
    public function getField();


    /**
     * Apply this construct to the given input and return the result
     *
     * @param array $inputValue will be an array of strings
     * @return mixed
     * @throws NoMatchingMapException if no input values to a map rule matched its mapping: this construct should not
     *                                apply any changes to this field
     * @throws SkipException          if this error is raised, no changes should be applied to any fields
     */
    public function apply($inputValue);
}
