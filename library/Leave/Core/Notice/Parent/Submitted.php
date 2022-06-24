<?php

/**
 * Leave_Core_Notice_Parent_Submitted
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Leave_Core_Notice_Parent_Submitted extends Leave_Core_Notice_Parent_Abstract
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
            [
                'LEAVE_SUBMITTED_BY' => $this->getRequest()->getAuthor()->formatName(),
            ]
        );
    }
}
