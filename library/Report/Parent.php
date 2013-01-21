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
	 * The name of someone you are searching for
	 *
	 * @var string
	 */
	private $searchName = '';

	/**
	 * The boarding status of someone you are searching for
	 *
	 * @var bool
	 */
	private $searchBoardingStatus = false;

	/**
	 * The form of someone you are searching for
	 *
	 * This seems to be some kind of arbitrary text string
	 * found in the Synergetic "School" tab
	 *
	 * @var string
	 */
	private $searchForm = '';

	/**
	 * Constructor
	 *
	 * @param $page
	 * @param $yearLevel
	 * @param $searchName
	 * @param $searchBoardingStatus
	 * @param $searchForm
	 */
	public function __construct($page, $yearLevel, $searchName, $searchBoardingStatus, $searchForm) {
		$this->setGroupHandler(new Group());
		$this->setYearLevel($yearLevel);
		$this->setSearchName($searchName);
		$this->setSearchBoardingStatus($searchBoardingStatus);
		$this->setSearchForm($searchForm);

		if (!$this->checkPermissions()) {
			error(155);
		}
		else {
			$this->setPage($page);

			$this->setExternalUserHandler(new External_User());
			$this->getExternalUserHandler()->getCurrentSemester();

			$this->setup();

			$this->setAllRecords($this->getExternalUserHandler()->getStudentContactListExtended($this->getYearLevel(), $this->getSearchName(), $this->getSearchBoardingStatus(), $this->getSearchForm()));
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

			/* If Restrict Parent Directory is on, the following conditions apply:
			 * If you are a parent, ensure that you have a child in the selected year level.
			 * If you don't have a child in this year level, you can't view it.
			 */
            if (Cfg::get('restrict_parent_directory')) {
                if (is_array($_SESSION['children']) && count($_SESSION['children'])) {
                    foreach($_SESSION['children'] as $currentChild) {
                        if ($currentChild['year'] == $this->getYearLevel()) {
                            return true;
                        }
                    }
                }
            }
			else {
                return true;
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
		$this->getPage()->setBlock(TEMPLATE_content, 'parent_directory_restriction_note', 'parent_directory_restriction_note_block');
		$this->getPage()->setBlock(TEMPLATE_content, 'use_parent_directory_validation', 'use_parent_directory_validation_block');
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
			'SEARCH_NAME' => $this->getSearchName(),
			'SEARCH_FORM' => $this->getSearchForm(),
			'SEARCH_YEAR' => $this->getYearLevel(),
		));

		/* Search boarding status dropdown preselection
		 */
		if ($this->getSearchBoardingStatus()) {
			$this->getPage()->setVar(array(
				'SEARCH_BOARDINGSTATUS_YES' => 'selected',
				'SEARCH_BOARDINGSTATUS_NO' => '',
			));
		}
		else {
			$this->getPage()->setVar(array(
				'SEARCH_BOARDINGSTATUS_YES' => '',
				'SEARCH_BOARDINGSTATUS_NO' => 'selected',
			));
		}

		/* Note about using Year Level in Parent Directory search options
		 */
		if (Cfg::get('restrict_parent_directory')) {
			$this->getPage()->parse('parent_directory_restriction_note_block', 'parent_directory_restriction_note');
			$this->getPage()->parse('use_parent_directory_validation_block', 'use_parent_directory_validation');
		}
		else {
			$this->getPage()->setVar('parent_directory_restriction_note_block', '');
			$this->getPage()->setVar('use_parent_directory_validation_block', '');
		}
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
						'STUDENT_YEAR' => $currentContact['StudentYearLevel'],
                        'STUDENT_BOARDER' => $currentContact['StudentBoarder'] == 'B' ? '(Boarder)' : '',
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

					/* Figure out whether to use the Preferred or the Given (LegalFullName) field
					 * for this contact
					 */
					if (strlen($currentContact['StudentContactPreferred'])) {
						$firstname = $currentContact['StudentContactPreferred'];
					}
					else {
						$firstname = $currentContact['StudentContactLegalFullName'];
					}

					$this->getPage()->setVar(array(
						'CONTACT_CONTACT_TYPE' => $currentContact['StudentContactType'],
						'CONTACT_TITLE' => $currentContact['StudentContactTitle'],
						'CONTACT_FIRSTNAME' => $firstname,
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

				if ((($currentContact['StudentContactSpousePreferred'] != '') || ($currentContact['StudentContactSpouseLegalFullName'] != '')) && ($currentContact['SpouseDirectoryIncludeFlag'] == 1)) {

					/* Figure out whether to use the Preferred or the Given (LegalFullName) field
					 * for this contact
					 */
					if (strlen($currentContact['StudentContactSpousePreferred'])) {
						$firstname = $currentContact['StudentContactSpousePreferred'];
					}
					else {
						$firstname = $currentContact['StudentContactSpouseLegalFullName'];
					}

					$this->getPage()->setVar(array(
						'SPOUSE_CONTACT_TYPE' => $currentContact['StudentContactSpouseType'],
						'SPOUSE_TITLE' => $currentContact['StudentContactSpouseTitle'],
						'SPOUSE_FIRSTNAME' => $firstname,
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

	/**
	 * Export parent list
	 *
	 * @param Report_Exporter $exporter
	 * @param string $format
	 */
    public function export(Report_Exporter $exporter, $format = 'csv') {
        // function stub to appease interpreter
        // can be implemented if export required.
    }

	/* Getters/Setters
	 */

	/**
	 * Set search boarding status
	 *
	 * @param boolean $searchBoardingStatus
	 */
	private function setSearchBoardingStatus($searchBoardingStatus) {
		$this->searchBoardingStatus = $searchBoardingStatus;
	}

	/**
	 * Get search boarding status
	 *
	 * @return boolean
	 */
	private function getSearchBoardingStatus() {
		return $this->searchBoardingStatus;
	}

	/**
	 * Set search form
	 *
	 * @param string $searchForm
	 */
	private function setSearchForm($searchForm) {
		$this->searchForm = $searchForm;
	}

	/**
	 * Get search form
	 *
	 * @return string
	 */
	private function getSearchForm() {
		return $this->searchForm;
	}

	/**
	 * Set search name
	 *
	 * @param string $searchName
	 */
	private function setSearchName($searchName) {
		$this->searchName = $searchName;
	}

	/**
	 * Get search name
	 *
	 * @return string
	 */
	private function getSearchName() {
		return $this->searchName;
	}
}

