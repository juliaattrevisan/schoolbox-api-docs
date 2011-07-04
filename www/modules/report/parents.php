<?php
require_once(dirname(__FILE__) . '/../../../include/config.php');
require_once(dirname(__FILE__) . '/../../../library/group.php');
require_once(dirname(__FILE__) . '/../../../library/synergetic/ExternalUser.php');
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
				$this->setVar(array(
					'CONTACT_CONTACT_TYPE' => $currentContact['StudentContactType'],
					'CONTACT_TITLE' => $currentContact['StudentContactTitle'],
					'CONTACT_FIRSTNAME' => $currentContact['StudentContactPreferred'],
					'CONTACT_SURNAME' => $currentContact['StudentContactSurname'],
					'CONTACT_PHONE' => $currentContact['StudentContactPhoneActual'],
					'CONTACT_MOBILE' => $currentContact['StudentContactMobilePhone'],
					'CONTACT_STREET' => $currentContact['StudentContactAddress1'],
					'CONTACT_SUBURB' => $currentContact['StudentContactSuburb'],
					'CONTACT_STATE' => $currentContact['StudentContactState'],
					'CONTACT_POSTCODE' => $currentContact['StudentContactPostCode'],
				));
                $showContact = true;
			    $this->parse('primary_block','primary');
            }
            else {
                $this->setVar('primary_block','');
            }

			if ($currentContact['StudentContactSpousePreferred'] != '' && $currentContact['SpouseDirectoryIncludeFlag'] == 1) {
                $this->setVar(array(
                    'SPOUSE_CONTACT_TYPE' => $currentContact['StudentContactSpouseType'],
                    'SPOUSE_TITLE' => $currentContact['StudentContactSpouseTitle'],
                    'SPOUSE_FIRSTNAME' => $currentContact['StudentContactSpousePreferred'],
                    'SPOUSE_SURNAME' => $currentContact['StudentContactSpouseSurname'],
                    'SPOUSE_PHONE' => $currentContact['StudentContactSpousePhoneActual'],
                    'SPOUSE_MOBILE' => $currentContact['StudentContactSpouseMobilePhone'],
                    'SPOUSE_STREET' => $currentContact['StudentContactSpouseAddress1'],
                    'SPOUSE_SUBURB' => $currentContact['StudentContactSpouseSuburb'],
                    'SPOUSE_STATE' => $currentContact['StudentContactSpouseState'],
                    'SPOUSE_POSTCODE' => $currentContact['StudentContactSpousePostCode'],
                ));
                $showContact = true;
                $this->parse('spouse_block', 'spouse');
            }
            else {
                $this->setVar('spouse_block', '');
            }

            if($showContact){
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
?>