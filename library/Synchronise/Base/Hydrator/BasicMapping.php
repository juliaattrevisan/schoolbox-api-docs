<?php
/**
 * A basic hydrator that just maps one field from $externalUser to another in
 * $newUser, with an optional format parameter.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_BasicMapping implements Synchronise_Base_IHydrator
{

    /**
     * Field name to map from.
     *
     * @var string
     */
    protected $externalUserField;

    /**
     * Field name to map to.
     *
     * @var string
     */
    protected $newUserField;

    /**
     * Formatting string used to convert the value of $externalUserField.
     * @see sprintf()
     *
     * @var string
     */
    protected $format = '%s';

    /**
     * @param string $externalUserField Field name to map from.
     * @param string $newUserField      Field name to map to.
     * @param string $format            Format to use in mapping.
     *                                  newField = sprintf(format, externalField)
     */
    public function __construct($externalUserField, $newUserField, $format = '%s')
    {
        $this->externalUserField = $externalUserField;
        $this->newUserField      = $newUserField;
        $this->format            = $format;
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser)
    {
        $newUser[$this->newUserField] = sprintf(
            $this->format,
            $externalUser[$this->externalUserField]
        );
        return $newUser;
    }
}
