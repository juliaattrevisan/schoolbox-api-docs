<?php
/**
 * A basic hydrator that just sets a value on a field.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_BasicValue implements Synchronise_Base_IHydrator
{

    /**
     * Field name to fill in.
     *
     * @var string
     */
    protected $newUserField;

    /**
     * Value to fill in.
     *
     * @var string
     */
    protected $value;

    /**
     * Formatting string used to convert the value of $externalUserField.
     * @see sprintf()
     *
     * @var string
     */
    protected $format = '%s';

    /**
     * @param string $newUserField Field name to map from.
     * @param string $value        Value to fill in.
     */
    public function __construct($newUserField, $value)
    {
        $this->newUserField = $newUserField;
        $this->value        = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser[$this->newUserField] = $this->value;
        return $newUser;
    }
}
