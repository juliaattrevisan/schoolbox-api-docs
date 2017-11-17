<?php
/**
 * ValidProfessionalDevelopment
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\ProfessionalDevelopment;

class ValidProfessionalDevelopment
{

    /**
     * @var Model
     */
    private $pd;

    /**
     * @var array
     */
    private $states;


    /**
     * ValidProfessionalDevelopment constructor.
     * @param array $states
     */
    public function __construct(array $states)
    {
        $this->states = $states;
    }


    /**
     * Conditions that determine a valid Professional Development number
     * Alphanumerics only; all other characters are removed
     *
     * @param $pdNumber
     * @return bool
     */
    private function isValidNumber($pdNumber)
    {
        $filtered = preg_replace('/[^a-zA-Z0-9]/', '', $pdNumber);

        if (strlen($filtered)) {
            return true;
        }

        return false;
    }


    /**
     * Conditions that determine a valid Professional Development registration state
     *
     * @param $state
     * @return bool
     */
    private function isValidRegistrationState($state)
    {
        if (in_array($state, $this->states)) {
            return true;
        }

        return false;
    }

    /**
     * Conditions that determine valid Professional Development data
     *
     * @param Model $pd
     * @return bool
     */
    public function isSatisfiedBy(Model $pd)
    {
        try {
            $number = $pd->getNumber();
            $state = $pd->getRegistrationState();

            return $this->isValidNumber($number) && $this->isValidRegistrationState($state);
        }
        catch (\Throwable $e) {
            return false;
        }
    }
}