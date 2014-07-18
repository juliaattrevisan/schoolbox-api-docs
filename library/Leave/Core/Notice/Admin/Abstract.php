<?php
/**
 * Base class for email notifications.
 *
 * @author     Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright  Copyright 2014, Alaress Pty Ltd
 * @link       http://www.alaress.com.au/
 * @copiedfrom Steve Chan <steve.chan@alaress.com.au>
 */
abstract class Leave_Core_Notice_Admin_Abstract extends Leave_Notice_Abstract_Notice {
    protected $email = null;
    protected $emailTemplateFile = '../../core/modules/leave/adminNoticeEmail.html';

    /**
     * Setup
     */
    public function setup() {
        $subject = '(Admin) Leave Request Status Update for ' . $this->getRequest()->getUser()->formatName();

        $email = new Mailer($this->getEmailTemplateFile(), $subject , $this->getRequest()->getUser()->getAltEmail());

        $email->setBlock($email->t_label, 'submitted_by', 'submitted_by_block');
        $email->setBlock($email->t_label, 'authorised_by', 'authorised_by_block');
        $email->setBlock($email->t_label, 'rejected_by', 'rejected_by_block');

        $email->setVar(array(
            'LEAVE_USER_NAME' => $this->getRequest()->getUser()->formatName(),
            'LEAVE_AUTHOR_NAME' => $this->getRequest()->getAuthor()->formatName(),
            'LEAVE_STATUS' => $this->getRequest()->getStatus(true),
            'LEAVE_START' => $this->getRequest()->getStart()->getLong(),
            'LEAVE_FINISH' => $this->getRequest()->getFinish()->getLong(),
            'LEAVE_DESCRIPTION' => $this->getRequest()->getDescription(),
            'LEAVE_HOST_NAME' => $this->getRequest()->getHostName(),
            'LEAVE_HOST_PHONE' => $this->getRequest()->getHostPhone(),
            'LEAVE_STUDENT_PHONE' => $this->getRequest()->getUserPhone(),
        ));

        $this->setEmail($email);
    }

    /**
     * Send it - by default, sends nothing (because most people don't have admin
     * messages set up
     */
    public function send() {
        return;
    }

    /* Getters/Setters
     */

    /**
     * Set email
     *
     * @param $email
     */
    protected function setEmail($email) {
        $this->email = $email;
    }

    /**
     * Get email
     *
     * @return null
     */
    protected function getEmail() {
        return $this->email;
    }
}
