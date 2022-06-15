<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Grammar\Condition;

use Alaress\ObjectReader;
use Alaress\Rules\Condition;
use Schoolbox\Collection;

class ExternalUserValueIn extends Condition\In
{

    /** @var string */
    protected $entityLabel = 'externalUser';


    /**
     * @param string           $field
     * @param array|string|int $validValues
     * @param bool             $negate
     */
    public function __construct($field, $validValues, $negate = false)
    {
        parent::__construct($field, $validValues, $negate);
    }


    /**
     * @param mixed $externalUser
     * @return bool
     */
    public function assertIn($externalUser)
    {
        $reader = new ObjectReader();
        $value  = $reader->read($externalUser, $this->field);

        // this occurs if the field being read is multi, such as
        // * emails and campus codes (array)
        // * positions (Collection)
        if (is_array($value) || $value instanceof Collection) {
            // require ANY, not ALL
            foreach ($value as $singleValue) {
                if ($this->matchesValidValue($singleValue)) {
                    return true;
                }
            }
            return false;
        }

        return $this->matchesValidValue($value);
    }


    /**
     * @return string
     */
    public function getSummary()
    {
        if (count($this->validValues) == 1 && is_null($this->validValues[0])) {
            $pattern = "$this->entityLabel.%s %sBLANK";
        } else {
            $pattern = count($this->validValues) == 1 ? "$this->entityLabel.%s %s= %s" : "$this->entityLabel.%s %sIN (%s)";
        }

        return sprintf($pattern, $this->field, $this->isNegated() ? 'NOT ' : '', implode(', ', $this->getValidValues()));
    }

    /**
     * Does `$value` match one of the valid values for this condition?
     *
     * @param mixed $value
     * @return bool
     */
    private function matchesValidValue($value): bool
    {
        foreach ($this->validValues as $validValue) {
            if ($value == $validValue) {
                return true;
            }
        }
        return false;
    }
}
