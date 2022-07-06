<?php

/**
 * Notice class for notifying admin of approved leave requests.
 *
 * Currently sends nothing because the admin was the person who leave the
 * request in the first place. People don't need to be notified of actions that
 * they took themselves - take note JPC.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Leave_Core_Notice_Admin_Authorised extends Leave_Core_Notice_Admin_Abstract
{
    public function send()
    {
        return;
    }
}
