<?php
/**
 * Leave_Kings_Notice_User_Abstract
 *
 * The base class for user notifications.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
abstract class Leave_Kings_Notice_User_Abstract extends Leave_Notice_Abstract_Notice {
	protected $email = null;
	protected $emailTemplateFile = '../../kings/modules/leave/userNoticeEmail.html';

	/**
	 * Setup
	 */
	public function setup() {
		$subject = '(Student) Leave Request Status Update for ' . $this->getRequest()->getUser()->formatName();

        $email = new Mailer($this->getEmailTemplateFile(), $subject , $this->getRequest()->getUser()->getAltEmail());

		$email->setBlock($email->t_label, 'submitted_by', 'submitted_by_block');
		$email->setBlock($email->t_label, 'authorised_by', 'authorised_by_block');
		$email->setBlock($email->t_label, 'rejected_by', 'rejected_by_block');

		$email->setVar(array(
			'LEAVE_USER_NAME' => $this->getRequest()->getUser()->formatName(),
			'LEAVE_STATUS' => $this->getRequest()->getStatus(true),
			'LEAVE_START' => $this->getRequest()->getStart()->getLong(),
			'LEAVE_FINISH' => $this->getRequest()->getFinish()->getLong(),
			'LEAVE_DESCRIPTION' => $this->getRequest()->getDescription(),
			'LEAVE_HOST_NAME' => $this->getRequest()->getHostName(),
			'LEAVE_HOST_PHONE' => $this->getRequest()->getHostPhone(),
			'LEAVE_STUDENT_PHONE' => $this->getRequest()->getUserPhone(),
			'TERMS_AND_CONDITIONS' => Cfg::get('terms_and_conditions'),
		));

		$this->setEmail($email);
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
