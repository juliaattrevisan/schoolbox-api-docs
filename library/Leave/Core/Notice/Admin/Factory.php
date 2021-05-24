<?php

/**
 * Builds notice classes for notifications for admin emails.
 *
 * @author     Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright  Copyright 2019, Schoolbox Pty Ltd
 * @link       https://schoolbox.com.au/
 * @copiedfrom Steve Chan <steve.chan@alaress.com.au>
 */
class Leave_Core_Notice_Admin_Factory extends Leave_Notice_Abstract_Factory
{

    /**
     * Produce a notification for the request based on status.
     *
     * @return Leave_Core_Notice_Admin_Abstract
     */
    public function produce()
    {
        $notice = null;
        // Admins only get notified if the request is new.
        // (it's reasonable to assume that boarder leave admin approved the request)
        $request = $this->getRequest();
        if ($request->isPending()) {
            $notice = new Leave_Core_Notice_Admin_Submitted($request);
            $notice->setup();
        } else if ($request->isApproved()) {
            $notice = new Leave_Core_Notice_Admin_Authorised($request);
            $notice->setup();
        } else if ($request->isRejected()) {
            $notice = new Leave_Core_Notice_Admin_Rejected($request);
            $notice->setup();
        }
        return $notice;
    }
}
