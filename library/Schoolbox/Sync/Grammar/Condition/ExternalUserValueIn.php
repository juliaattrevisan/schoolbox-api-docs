<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Sync\Grammar\Condition;

use Alaress\ObjectReader;
use Alaress\Rules\Condition;
use Schoolbox\User;

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

        return in_array($value, $this->validValues);
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

}
