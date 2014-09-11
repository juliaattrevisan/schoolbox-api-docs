<?php
/**
 * Unsets certain fields when the user is updated.
 *
 * This ensures that field values can be set with a default on create, but that
 * default is unset if the user is created.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_UnsetOnUpdate extends Synchronise_Base_Hydrator_CreateUpdate
{
    /**
     * The fields to clear from the updated user entry.
     *
     * @var array
     */
    protected $clearFields = array();

    /**
     * @param  array $clearFields
     * @return $this
     */
    public function setClearFields($clearFields)
    {
        $this->clearFields = $clearFields;
        return $this;
    }

    /**
     * @return array
     */
    public function getClearFields()
    {
        return $this->clearFields;
    }

    /**
     * {@inheritdoc}
     */
    protected function hydrateOnUpdate($externalUser, $newUser)
    {
        foreach ($this->getClearFields() as $clearField) {
            unset($newUser[$clearField]);
        }
        return $newUser;
    }
}
