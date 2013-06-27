<?php
/**
 * Leave_Core_Notice_Parent_Abstract
 *
 * The base class for parent notifications.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Leave_Core_Notice_Parent_Abstract extends Leave_Notice_Abstract_Notice {
    protected $email = null;
    protected $emailTemplateFile = '../../core/modules/leave/parentNoticeEmail.html';

    private $externalUser = null;

    /**
     * Setup
     */
    public function setup() {

        /* For getting all the parents of a child
           */
        $this->setExternalUser(new External_User());

        $subject = '(Parent) Leave Request Status Update for ' . $this->getRequest()->getUser()->formatName();

        /* Set destination email address in loop in send()
           */
        $email = new Mailer($this->getEmailTemplateFile(), $subject, '');
        $email->setFromAsCurrentUser();

        $email->setBlock($email->t_label, 'submitted_by', 'submitted_by_block');
        $email->setBlock($email->t_label, 'authorised_by', 'authorised_by_block');
        $email->setBlock($email->t_label, 'rejected_by', 'rejected_by_block');

        $email->setVar(
            array(
                'LEAVE_USER_NAME' => $this->getRequest()->getUser()->formatName(),
                'LEAVE_STATUS' => $this->getRequest()->getStatus(true),
                'LEAVE_START' => $this->getRequest()->getStart()->getLong(),
                'LEAVE_FINISH' => $this->getRequest()->getFinish()->getLong(),
                'LEAVE_DESCRIPTION' => $this->getRequest()->getDescription(),
                'LEAVE_HOST_NAME' => $this->getRequest()->getHostName(),
                'LEAVE_HOST_PHONE' => $this->getRequest()->getHostPhone(),
                'LEAVE_STUDENT_PHONE' => $this->getRequest()->getUserPhone(),
                'TERMS_AND_CONDITIONS' => Cfg::get('terms_and_conditions'),
            )
        );

        $this->setEmail($email);
    }

    /**
     * Send a notification to all parents of the child
     */
    public function send() {
        $allParents = $this->getExternalUser()->getStudentContactList($this->getRequest()->getUser()->getExternalId());

        if (is_array($allParents)) {
            foreach ($allParents as $parent) {
                if (Cfg::get('debug_email_address') != '') {
                    $this->getEmail()->send(Cfg::get('debug_email_address'));
                } else {
                    $this->getEmail()->send($parent['StudentContactDefaultEmail']);
                }
            }
        }
    }

//	public function send() {
//		$parentList = $this->getExternalUser()->getStudentContactList($this->getRequest()->getUser()->getSynergyId());
//
//		if (is_array($parentList)) {
//			foreach ($parentList as $parent) {
//				if (Cfg::get('debug_email_address') != '') {
//					$this->getEmail()->send(Cfg::get('debug_email_address'));
//				}
//				else {
//					$this->getEmail()->send($parent['StudentContactDefaultEmail']);
//				}
//			}
//		}
//	}

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

    /**
     * @param $externalUser
     */
    private function setExternalUser($externalUser) {
        $this->externalUser = $externalUser;
    }

    /**
     * @return null
     */
    private function getExternalUser() {
        return $this->externalUser;
    }
}
