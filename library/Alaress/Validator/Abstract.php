<?php

/**
 * Alaress_Validator_Abstract
 *
 * The base class for all validators within the system.
 *
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
abstract class Alaress_Validator_Abstract implements Alaress_Validator_ValidatorInterface
{

    protected $options = array();
    protected $value   = null;


    /**
     * Creates the validator, and sets configuration options.
     *
     * @param array $options Configuration options which may be used by child validators.
     */
    public function __construct($options = array())
    {
        $this->options = $options;
    }


    /**
     * Sets the value to validate into this object, so that helper methods called by isValid() may access it.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function setValue($value)
    {
        $this->value = $value;
    }


    /**
     * Validates $value.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function isValid($value)
    {
        $this->setValue($value);
        return true;
    }
}
