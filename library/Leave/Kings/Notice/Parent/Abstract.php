<?php
/**
 * Leave_Kings_Notice_Parent_Abstract
 *
 * The base class for parent notifications.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */

use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;

abstract class Leave_Kings_Notice_Parent_Abstract extends Leave_Notice_Abstract_Notice {
	protected $email = null;
	protected $emailTemplateFile = '../../kings/modules/leave/parentNoticeEmail.html';

	private $externalUser = null;

	/**
	 * Setup
	 */
    public function setup()
    {
		/* For getting all the parents of a child
		 */
		$this->setExternalUser(new External_User());

        // Housemaster - I can only really work with one
        $accessMapper = new UserAccessMapper();
        foreach ($accessMapper->findUsersForService(
            UserAccess::SERVICE_HOUSEMASTER,
            $this->getRequest()->getUser()->getResidentialHouse()->getId()
        ) as $housemaster) {
            $housemasterName  = $housemaster->formatName();
            $housemasterEmail = $housemaster->getEmail();

            $subject = '(Parent) Leave Request Status Update for '
                . $this->getRequest()->getUser()->formatName();

		/* Set destination email address in loop in send()
		 */
        $email = new Mailer($this->getEmailTemplateFile(), $subject, '');

		$email->setFrom($housemasterEmail, $housemasterName);

		$email->setBlock($email->t_label, 'submitted_by', 'submitted_by_block');
		$email->setBlock($email->t_label, 'authorised_by', 'authorised_by_block');
		$email->setBlock($email->t_label, 'rejected_by', 'rejected_by_block');

            $email->setVar(
                array(
                    'LEAVE_USER_NAME' => $this->getRequest()->getUser()->formatName(),
                    'LEAVE_AUTHOR_NAME' => $this->getRequest()->getAuthor()->formatName(),
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

            // only send one email from housemaster
            break;
        }
	}

	/**
	 * Send a notification to all parents of the child
	 */
	public function send() {
		$allEmails = $this->getExternalUser()->getParentEmails(
			array($this->getRequest()->getUser()->getExternalId())
		);

		if (is_array($allEmails) && count($allEmails)) {
			foreach($allEmails as $currentEmail) {
				if (Cfg::get('debug_email_address') != '') {
					$email = new \Email(Cfg::get('debug_email_address'));
				}
				else {
					$email =  new \Email($currentEmail);
				}

				/* Only send if the email is valid
				 */
				if ($email->isValid()) {
					$this->getEmail()->send($email->getEmail());
				}
			}
		}
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
