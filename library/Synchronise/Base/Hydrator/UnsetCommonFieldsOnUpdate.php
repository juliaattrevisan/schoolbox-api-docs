<?php
/**
 * Unsets commonly cleared fields when user is updated, and leaves them be when
 * user is created.
 *
 * The following fields are unset:
 * * su (superuser field)
 * * enabled
 * * eportfolio_enabled
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Base_Hydrator_UnsetCommonFieldsOnUpdate extends Synchronise_Base_Hydrator_UnsetOnUpdate {
    protected $clearFields = array('su', 'enabled', 'eportfolio_enabled');
}
