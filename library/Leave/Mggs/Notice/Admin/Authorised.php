<?php
/**
 * Notice class for notifying admin of approved leave requests.
 *
 * Currently sends nothing because the admin was the person who leave the
 * request in the first place. People don't need to be notified of actions that
 * they took themselves - take note JPC.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Leave_Mggs_Notice_Admin_Authorised extends Leave_Mggs_Notice_Admin_Abstract {

    public function send() {
        return;
    }
}
