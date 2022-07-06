<?php

/**
 * Notice class for notifying admin of rejected leave requests.
 *
 * Currently sends nothing because the admin was the person who rejected the
 * request in the first place. People don't need to be notified of actions that
 * they took themselves - take note JPC.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Leave_Core_Notice_Admin_Rejected extends Leave_Core_Notice_Parent_Abstract
{
    public function send()
    {
        return;
    }
}
