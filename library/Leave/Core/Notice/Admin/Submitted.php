<?php

/**
 * Notice class for notifying admin of new leave request.
 *
 * @author     Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright  Copyright 2019, Schoolbox Pty Ltd
 * @link       https://schoolbox.com.au/
 * @copiedfrom Steve Chan <steve.chan@alaress.com.au>
 */
class Leave_Core_Notice_Admin_Submitted extends Leave_Core_Notice_Admin_Abstract
{
    /**
     * Setup
     */
    public function setup()
    {
        /* Common replacements
         */
        parent::setup();

        /* Custom replacements
         */
        $this->email->setContext(
            ['LEAVE_SUBMITTED_BY' => $this->getRequest()->getAuthor()->formatName()]
        );
    }
}
