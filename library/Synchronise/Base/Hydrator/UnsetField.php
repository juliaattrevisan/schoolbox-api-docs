<?php
/**
 * Unsets a fields that was set earlier.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_UnsetField implements Synchronise_Base_IHydrator
{
    protected $field;

    public function __construct($field)
    {
        $this->field = $field;
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        unset($newUser[$this->field]);
        return $newUser;
    }
}
