<?php

/**
 * Alaress_Validator_ValidatorInterface
 *
 * Defines the minimum interface that all validator classes must implement.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
interface Alaress_Validator_ValidatorInterface
{
    /**
     * Validates $value.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function isValid($value);
}
