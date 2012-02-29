<?php
/**
 * Report_Parent
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Report_Parent extends Report_Abstract {

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $yearLevel
	 */
	public function __construct($page, $yearLevel) {
		$this->setGroupHandler(new Group());
		$this->setYearLevel($yearLevel);

		if (!is_numeric($yearLevel) || !$this->checkPermissions()) {
			error(19);
		}
		else {
			$this->setPage($page);

			$this->setExternalUserHandler(new External_User());
			$this->getExternalUserHandler()->getCurrentSemester();

			$this->setup();

			$this->setAllRecords($this->getExternalUserHandler()->getStudentContactListExtended($this->getYearLevel()));
		}
	}

	/**
	 * Check permissions. By default only parents and superusers can view this page, but
	 * for Parent Directory, the parent must have a child in the specified year level.
	 *
	 * @return bool
	 */
	protected function checkPermissions() {
		if ($this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff()) {
			return true;
		}
		else if ($this->getGroupHandler()->isParent()) {

			/* If you are a parent, ensure that you have a child in the selected year level.
			 * If you don't have a child in this year level, you can't view it.
			 */
			if (is_array($_SESSION['children']) && count($_SESSION['children'])) {
				foreach($_SESSION['children'] as $currentChild) {
					if ($currentChild['year'] == $this->getYearLevel()) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Set up blocks and stuff
	 */
	protected function setup() {
		$this->getPage()->setFile(TEMPLATE_content, 'modules/report/parents.html');
		$this->getPage()->setBlock(TEMPLATE_content, 'student', 'student_block');
		$this->getPage()->setTitle('Parent Contact info');
		$this->getPage()->setBlock('student', 'contact', 'contact_block');
		$this->getPage()->setBlock('contact', 'spouse', 'spouse_block');
		$this->getPage()->setBlock('contact', 'primary', 'primary_block');

		$this->getPage()->setVar(array(
			'student_block' => '',
			'contact_block' => '',
		));

		$this->getPage()->setVar(array(
			'YEAR' => $this->getYearLevel(),
		));
	}

	/**
	 * Render data
	 */
	public function render() {
		$allContacts = $this->getAllRecords();

		$showContact = false;

		if (is_array($allContacts) && count($allContacts)) {
			for ($lastID = -1, $i = 0; $i < count($allContacts); $i++) {
				$currentContact = $allContacts[$i];

				/* New student, or first student
				 */
				if ($lastID != $currentContact['StudentID']) {
					if ($i) {
						if ($showContact == false) {
							$this->getPage()->setVar(array(
								'PRIVATE' => 'private'
							));
						}
						else {
							$this->getPage()->setVar(array(
								'PRIVATE' => ''
							));
						}

						$this->getPage()->parse('student_block', 'student', true);
					}

					/* Clear children blocks after parsing out the parent
					 */
					$this->getPage()->setVar('contact_block', '');

					$showContact = false;

					$this->getPage()->setVar(array(
						'STUDENT_FIRSTNAME' => $currentContact['StudentGiven1'],
						'STUDENT_SURNAME' => $currentContact['StudentSurname'],
					));

					/* Include Student House (there's also a BoardingHouse element
					 * that could also be used?)
					 */
					if (strlen(trim($currentContact['StudentHouse']))) {
						$this->getPage()->setVar(array(
							'STUDENT_HOUSE' => '(' . $currentContact['StudentHouse'] . ')'
						));
					}
					else {
						$this->getPage()->setVar(array(
							'STUDENT_HOUSE' => ''
						));
					}
				}

				if ($currentContact['PrimaryDirectoryIncludeFlag'] == 1) {
					$address = '';

					/* Combine additional address details
					 * strlen/trim check in case of whitespace characters coming out
					 */
					if (strlen(trim($currentContact['StudentContactAddress1']))) {
						$address .= $currentContact['StudentContactAddress1'];
					}

					if (strlen(trim($currentContact['StudentContactAddress2']))) {
						$address .= '<br />' . $currentContact['StudentContactAddress2'];
					}

					if (strlen(trim($currentContact['StudentContactAddress3']))) {
						$address .= '<br />' . $currentContact['StudentContactAddress3'];
					}

					$address .= '<br />';

					/* Check if Country was specified
					 */
					if ($currentContact['StudentContactCountryDesc'] == 'Not Selected') {
						$country = '';
					}
					else {
						$country = $currentContact['StudentContactCountryDesc'];
					}

					$this->getPage()->setVar(array(
						'CONTACT_CONTACT_TYPE' => $currentContact['StudentContactType'],
						'CONTACT_TITLE' => $currentContact['StudentContactTitle'],
						'CONTACT_FIRSTNAME' => $currentContact['StudentContactPreferred'],
						'CONTACT_SURNAME' => $currentContact['StudentContactSurname'],
						'CONTACT_PHONE' => $currentContact['StudentContactSilentPhoneFlag']?'':$currentContact['StudentContactPhoneActual'],
						'CONTACT_MOBILE' => $currentContact['StudentContactMobilePhone'],
						'CONTACT_STREET' => $address,
						'CONTACT_SUBURB' => $currentContact['StudentContactSuburb'],
						'CONTACT_STATE' => $currentContact['StudentContactState'],
						'CONTACT_POSTCODE' => $currentContact['StudentContactPostCode'],
						'CONTACT_COUNTRY' => $country,
						'CONTACT_EMAIL' => $currentContact['StudentContactDefaultEmail'],
					));

					$showContact = true;

					$this->getPage()->parse('primary_block','primary');
				}
				else {
					/* Clear fields for spouse
					 */
					$this->getPage()->setVar(array(
						'CONTACT_PHONE' => '',
						'CONTACT_MOBILE' => '',
						'CONTACT_STREET' => '',
						'CONTACT_SUBURB' => '',
						'CONTACT_STATE' => '',
						'CONTACT_POSTCODE' => '',
						'CONTACT_COUNTRY' => '',
						'CONTACT_EMAIL' => '',
					));

					$this->getPage()->setVar('primary_block', '');
				}

				if ($currentContact['StudentContactSpousePreferred'] != '' && $currentContact['SpouseDirectoryIncludeFlag'] == 1) {
					$this->getPage()->setVar(array(
						'SPOUSE_CONTACT_TYPE' => $currentContact['StudentContactSpouseType'],
						'SPOUSE_TITLE' => $currentContact['StudentContactSpouseTitle'],
						'SPOUSE_FIRSTNAME' => $currentContact['StudentContactSpousePreferred'],
						'SPOUSE_SURNAME' => $currentContact['StudentContactSpouseSurname'],
						'SPOUSE_CONTACT_MOBILE' => $currentContact['StudentContactSpouseMobilePhone'],
						'SPOUSE_CONTACT_EMAIL' => $currentContact['StudentContactSpouseDefaultEmail'],
					));

					$showContact = true;

					$this->getPage()->parse('spouse_block', 'spouse');
				}
				else {
					$this->getPage()->setVar('spouse_block', '');
				}

				if ($showContact === true){
					$this->getPage()->parse('contact_block', 'contact', true);
				}
				else {
					$this->getPage()->setVar('contact_block', '');
				}

				$lastID = $currentContact['StudentID'];
			}

			/* Last parse for final element
			 */
			if ($showContact == false) {
				$this->getPage()->setVar(array(
					'PRIVATE' => 'private'
				));
			}
			else {
				$this->getPage()->setVar(array(
					'PRIVATE' => ''
				));
			}

			$this->getPage()->parse('student_block', 'student', true);
		}
	}
}



