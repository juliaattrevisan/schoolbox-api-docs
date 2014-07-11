<?php
/**
 * A basic hydrator that just maps one field from $externalUser to another in
 * $newUser.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_BasicMapping implements Synchronise_Base_IHydrator {

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
     * @param string $externalUserField Field name to map from.
     * @param string $newUserField      Field name to map to.
     */
    public function __construct($externalUserField, $newUserField) {
        $this->externalUserField = $externalUserField;
        $this->newUserField      = $newUserField;
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate($externalUser, $newUser) {
        $newUser[$this->newUserField] = $externalUser[$this->externalUserField];
        return $newUser;
    }
}
