<?php

/**
 * Leave_Core_Notice_Parent_Rejected
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Leave_Core_Notice_Parent_Rejected extends Leave_Core_Notice_Parent_Abstract
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
        $context = [
            'LEAVE_REJECTED_BY' => $this->getRequest()->getAuthorisationUser()->formatName(),
        ];

        $this->email->setContext($context);
    }
}
