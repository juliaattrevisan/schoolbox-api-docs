<?php

/**
 * Leave_Core_Notice_Parent_Authorised
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Leave_Core_Notice_Parent_Authorised extends Leave_Core_Notice_Parent_Abstract
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
                'LEAVE_AUTHORISED_BY' => $this->getRequest()->getAuthorisationUser()->formatName(),
                'LEAVE_CONFIRMED_BY'  => $this->getRequest()->getConfirmedByMapIndex($this->getRequest()->getConfirmedBy()),
            ]
        );
    }
}
