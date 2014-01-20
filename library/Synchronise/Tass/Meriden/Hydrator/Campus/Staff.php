<?php
/**
 * Hydrator for campus for Meriden staff users.
 *
 * Rules are exactly the same as for other users, but instead runs from the
 * 'StaffCampus' field.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_Tass_Meriden_Hydrator_Campus_Staff extends Synchronise_Tass_Meriden_Hydrator_Campus {
    protected $field = 'StaffCampus';
}