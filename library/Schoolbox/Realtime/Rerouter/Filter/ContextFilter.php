<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Rerouter\Filter;


abstract class ContextFilter implements FilterInterface {

	/**
	 * Tests the overlap of a context value with the corresponding data from the user object
	 *
	 * @param array|string $contextValue
	 * @param array|string $comparisonValue
	 * @return bool
	 */
	protected function testContextValue($contextValue, $comparisonValue) {
		if(is_array($contextValue)) {
			if(is_array($comparisonValue)) {
				return !!count(array_intersect($contextValue, $comparisonValue));
			}
			return in_array($comparisonValue, $contextValue);
		}

		if(is_array($comparisonValue)) {
			return in_array($contextValue, $comparisonValue);
		}
		return $contextValue == $comparisonValue;
	}

} 