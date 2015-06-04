<?php
/**
 * Sheldon-specific external user class
 *
 * Currently does nothing different except have a different value for split
 * parent ID length.
 *
 * (Sheldon parent IDs are 5 digits long, and this can't be changed.)
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Tass_Sheldon_User extends External_Tass_User
{
    const SPLIT_PARENT_TASSID_LENGTH = 6;
}
