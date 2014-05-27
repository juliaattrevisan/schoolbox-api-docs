<?php
 /**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


namespace Alaress\Async\Notification\Parameter;


use JsonSerializable;

interface ParameterInterface extends JsonSerializable {

	/**
	 * Use whichever parameter comes in first. Implies all will be identical.
	 */
	const STACKING_IGNORE  = -1;

	/**
	 * Use the MOST RECENT (highest timestamp) parameter given
	 */
	const STACKING_REPLACE = 0;

	/**
	 * Use all parameters given by stacking.
	 * If you stack more than one parameter per notification,
	 * be sure to check edge-cases for unintended side effects.
	 */
	const STACKING_APPEND  = 1;


    /**
     * @return string
     */
    public function getText();


    /**
     * @return string
     */
    public function getHref();


    /**
     * @return int
     */
    public function getMode();

}