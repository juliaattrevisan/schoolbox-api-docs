<?php
/**
 * Cathedral-specific external user class
 *
 * Extended because Cathedral has some logic for sync scripts that I don't
 * necessarily want to introduce to Meriden, or other TASS schools.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Tass_Cathedral_User extends External_Tass_User
{
    /**
     * @var External_Tass_UserDelegate
     */
    protected $syncDelegate;

    public function __construct()
    {
        parent::__construct();
        $this->syncDelegate = new External_Tass_UserDelegate($this->getDatabase());
    }

    /**
     * Retrieves staff user account info for sync script.
     *
     * Main difference from standard TASS sync: ActiveFlag uses term_date
     * instead of curr_flg.
     *
     * @return array
     */
    public function getStaffUserAccountInfo()
    {

        return $this->syncDelegate->getStaffUserAccountInfo();
    }

    /**
     * Retrieves parent user account info for sync script.
     *
     * Main difference from standard TASS sync: students are fetched from and
     * associated with students based on Split Family Access table.
     *
     * @return array
     */
    public function getParentUserAccountInfo()
    {
        return $this->syncDelegate->getParentUserAccountInfo();
    }
}
