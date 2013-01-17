<?php
/**
 * Alaress_Validator_ValidatableInterface
 *
 * Defines the minimum interface that all classes that may be validated with Alaress validators must implement.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
interface Alaress_Validator_ValidatableInterface {
//	public function addValidator(Alaress_Validator_ValidatorInterface $validator);
	public function isValid();
}
