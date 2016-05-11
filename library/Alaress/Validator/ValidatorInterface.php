<?php
/**
 * Alaress_Validator_ValidatorInterface
 *
 * Defines the minimum interface that all validator classes must implement.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
interface Alaress_Validator_ValidatorInterface {
	/**
	 * Validates $value.
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public function isValid($value);
}
