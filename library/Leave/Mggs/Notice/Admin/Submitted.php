<?php
/**
 * Notice class for notifying admin of new leave request.
 *
 * @author     Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright  Copyright 2014, Alaress Pty Ltd
 * @link       http://www.alaress.com.au/
 * @copiedfrom Steve Chan <steve.chan@alaress.com.au>
 */
class Leave_Mggs_Notice_Admin_Submitted extends Leave_Mggs_Notice_Admin_Abstract {

    /**
     * Send it to someone@mggs.vic.edu.au
     */
    public function send() {
        $mail = $this->getEmail();
        $mail->setTo('Boarders.Leave@mggs.vic.edu.au');
        $mail->send();
    }

}
