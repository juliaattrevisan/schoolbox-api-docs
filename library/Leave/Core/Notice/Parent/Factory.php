<?php

/**
 * Leave_Core_Notice_Parent_Factory
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Leave_Core_Notice_Parent_Factory extends Leave_Notice_Abstract_Factory
{

    /**
     * Produce a family of Kings Parent Notifications based on status
     *
     * @return Leave_Core_Notice_Parent_Authorised|Leave_Core_Notice_Parent_Rejected|Leave_Core_Notice_Parent_Submitted|null
     */
    public function produce()
    {
        $notice = null;

        if ($this->getRequest()->isPending()) {
            $notice = new Leave_Core_Notice_Parent_Submitted($this->getRequest());
            $notice->setup();
        } else if ($this->getRequest()->isApproved()) {
            $notice = new Leave_Core_Notice_Parent_Authorised($this->getRequest());
            $notice->setup();
        } else if ($this->getRequest()->isRejected()) {
            $notice = new Leave_Core_Notice_Parent_Rejected($this->getRequest());
            $notice->setup();
        }

        return $notice;
    }
}
