<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\ProfessionalDevelopment;

/**
 * Class ValidProfessionalDevelopment
 *
 * @package Schoolbox\ProfessionalDevelopment
 */
class ValidProfessionalDevelopment
{

    /**
     * @var array
     */
    private $states;


    /**
     * ValidProfessionalDevelopment constructor.
     *
     * @param array $states
     */
    public function __construct(array $states)
    {
        $this->states = $states;
    }


    /**
     * Conditions that determine a valid Professional Development number
     * Alphanumerics only; if it contains anything else, it's invalid
     *
     * @param string $pdNumber
     * @return bool
     */
    private function isValidNumber($pdNumber): bool
    {
        return preg_match('/^[a-zA-Z0-9]+$/', $pdNumber);
    }


    /**
     * Conditions that determine a valid Professional Development registration state
     * Must be one of the $states provided in constructor
     *
     * @param string $state
     * @return bool
     */
    private function isValidRegistrationState($state): bool
    {
        return \in_array($state, $this->states, true);
    }


    /**
     * Conditions that determine valid Professional Development data
     *
     * @param Model $pd
     * @return bool
     */
    public function isSatisfiedBy(Model $pd): bool
    {
        try {
            $number = $pd->getNumber();
            $state = $pd->getRegistrationState();

            return $this->isValidNumber($number) && $this->isValidRegistrationState($state);
        } catch (\Throwable $e) {
            // In case it came out of the db as null etc.
            return false;
        }
    }
}
