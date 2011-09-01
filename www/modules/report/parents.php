<?php
require_once(dirname(__FILE__) . '/../../../include/Config.php');
require_once(dirname(__FILE__) . '/../../../library/group.php');
require_once( dirname(__FILE__) . '/../../../library/Synergetic/ExternalUser.php' );
require_once(dirname(__FILE__) . '/../../include/stdfuncs.php');

$this->setTitle('Parent Contact info');
$this->setFile(TEMPLATE_content, 'modules/report/parents.html');
$this->setBlock(TEMPLATE_content, 'student', 'student_block');
$this->setBlock('student', 'contact', 'contact_block');
$this->setBlock('contact', 'spouse', 'spouse_block');
$this->setBlock('contact', 'primary', 'primary_block');

$this->setVar(array(
	'student_block' => '',
	'contact_block' => '',
));

if (!is_numeric($this->arg[0])) {
	error(19);
}

/* Only parents and superusers can view this page
 */
$groupHandler = new Group();

if ($groupHandler->isParent() || $groupHandler->isSuperuser() || $groupHandler->isStaff()) {

	$this->setVar(array(
		'YEAR' => $this->arg[0],
	));

	$externalUserHandler = new ExternalUser();
	$externalUserHandler->getCurrentSemester();

	$allContacts = $externalUserHandler->getStudentContactListExtended($this->arg[0]);

	if (is_array($allContacts) && count($allContacts)) {
		for ($lastID = -1, $i = 0; $i < count($allContacts); $i++) {
			$currentContact = $allContacts[$i];

			/* New student, or first student
			 */
			if ($lastID != $currentContact['StudentID']) {
				if ($i) {
					$this->parse('student_block', 'student', true);
				}

				/* Clear children blocks after parsing out the parent
				 */
				$this->setVar('contact_block', '');

                $showContact = false;

				$this->setVar(array(
					'STUDENT_FIRSTNAME' => $currentContact['StudentGiven1'],
					'STUDENT_SURNAME' => $currentContact['StudentSurname'],
				));
			}

			if ($currentContact['PrimaryDirectoryIncludeFlag'] == 1) {
				$address = $currentContact['StudentContactAddress1'];

				/* Combine additional address details
				 */
				if ($currentContact['StudentContactAddress2'] != '') {
					$address .= ',<br />' . $currentContact['StudentContactAddress2'];
				}

				if ($currentContact['StudentContactAddress3'] != '') {
					$address .= ',<br />' . $currentContact['StudentContactAddress3'];
				}

				$this->setVar(array(
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
					'CONTACT_COUNTRY' => $currentContact['StudentContactCountryDesc'],
					'CONTACT_EMAIL' => $currentContact['StudentContactDefaultEmail'],
				));

                $showContact = true;

			    $this->parse('primary_block','primary');
            }
            else {
				/* Clear fields for spouse
				 */
				$this->setVar(array(
					'CONTACT_PHONE' => '',
					'CONTACT_MOBILE' => '',
                    'CONTACT_STREET' => '',
                    'CONTACT_SUBURB' => '',
                    'CONTACT_STATE' => '',
                    'CONTACT_POSTCODE' => '',
					'CONTACT_COUNTRY' => '',
					'CONTACT_EMAIL' => '',
				));

                $this->setVar('primary_block', '');
            }

			if ($currentContact['StudentContactSpousePreferred'] != '' && $currentContact['SpouseDirectoryIncludeFlag'] == 1) {
                $this->setVar(array(
                    'SPOUSE_CONTACT_TYPE' => $currentContact['StudentContactSpouseType'],
                    'SPOUSE_TITLE' => $currentContact['StudentContactSpouseTitle'],
                    'SPOUSE_FIRSTNAME' => $currentContact['StudentContactSpousePreferred'],
                    'SPOUSE_SURNAME' => $currentContact['StudentContactSpouseSurname'],
                    'SPOUSE_CONTACT_MOBILE' => $currentContact['StudentContactSpouseMobilePhone'],
					'SPOUSE_CONTACT_EMAIL' => $currentContact['StudentContactSpouseDefaultEmail'],
                ));

                $showContact = true;

                $this->parse('spouse_block', 'spouse');
            }
            else {
                $this->setVar('spouse_block', '');
            }

            if ($showContact === true){
				$this->parse('contact_block', 'contact', true);
			}
			else {
				$this->setVar('contact_block', '');
			}

			$lastID = $currentContact['StudentID'];
		}

		/* Last parse for final element
		 */
		$this->parse('student_block', 'student', true);
	}
}
else {
	error(19);
}