<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
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
     */
    public function apply($inputValue);

}
