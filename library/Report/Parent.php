<?php

use Alaress\Mail\Mailer;
use Schoolbox\Error;

/**
 * Report_Parent
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Report_Parent extends Report_Abstract
{
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
     * Options for boarding status
     *
     * @var array
     */
    private $boardingStatusOptions = array('any', 'yes', 'no');

    /**
     * All available year levels from SIS
     *
     * @var array
     */
    protected $sisAllYearLevels;

    /**
     * Allowed searchable years for parents
     *
     * @var array
     */
    protected $allowedSearchableYears;

    /**
     * Report_Parent constructor.
     * @param SitePage $page
     * @param string $yearLevel
     * @param string $searchName
     * @param string $searchBoardingStatus
     * @param string $searchForm
     * @throws Exception
     */
    public function __construct($page, $yearLevel, $searchName, $searchBoardingStatus, $searchForm)
    {
        $this->setGroupHandler(new Group());

        $this->setExternalUserHandler(new External_User());

        $yearLevel = trim($yearLevel);
        $this->sisAllYearLevels = $this->getExternalUserHandler()->getAllYearLevels();
        $this->allowedSearchableYears = $this->filterToStudentYearLevels($this->getChildrensYears(), $this->sisAllYearLevels);
        $yearColumn = array_column($this->allowedSearchableYears, 'Code');

        // Parents may only search in their student's year level
        if ($this->isYearsRestricted() && !(in_array($yearLevel, $yearColumn) || $yearLevel == '')) {
            error(Error\Mapper::ERROR_PERM_PARENT_DIRECTORY_YEAR_LEVEL);
        }

        $this->setYearLevel(trim($yearLevel));
        $this->setSearchName(trim($searchName));
        $this->setSearchBoardingStatus(trim($searchBoardingStatus));
        $this->setSearchForm(trim($searchForm));

        $searching = strlen(
            $this->getYearLevel()
            . $this->getSearchName()
            . $this->getSearchForm()
            . $this->getSearchBoardingStatus()
        ) > 0;
        if ($searching && !$this->checkPermissions()) {
            error(Error\Mapper::ERROR_PERM_PARENT_DIRECTORY_YEAR_LEVEL);
        } else {
            $this->setPage($page);


            $this->setup();

            /* If all four options are empty, just show search form - don't show
             * all results (default is empty array)
             */
            if ($searching) {
                $this->setAllRecords(
                    $this->getExternalUserHandler()->getStudentContactListExtended(
                        $this->getYearLevel(),
                        $this->getSearchName(),
                        $this->getSearchBoardingStatus(),
                        $this->getSearchForm()
                    )
                );
            }

            // check that something came back, otherwise inform the user that it was an invalid request
            if ($this->getAllRecords() === false) {
                error(Error\Mapper::ERROR_PERM_PARENT_DIRECTORY_YEAR_LEVEL);
            }
        }
    }

    /**
     * Check permissions. By default only parents and superusers can view this page, but
     * for Parent Directory, the parent must have a child in the specified year level.
     *
     * @return bool
     */
    protected function checkPermissions(): bool
    {
        if ($this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff()) {
            return true;
        } else if ($this->getGroupHandler()->isParent()) {
            /* If Restrict Parent Directory is on, the following conditions apply:
             * If you are a parent, ensure that you have a child in the selected year level.
             * If you don't have a child in this year level, you can't view it.
             */
            if (Config::get('restrict_parent_directory')) {
                if (is_array($_SESSION['children']) && count($_SESSION['children'])) {
                    foreach ($_SESSION['children'] as $currentChild) {
                        $student = $this->getExternalUserHandler()->getStudent($currentChild['sid']);
                        if ($student['StudentYearLevel'] == $this->getYearLevel()) {
                            return true;
                        }
                    }
                }
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Set up blocks and stuff
     */
    protected function setup()
    {
        $this->getPage()->setFile(TEMPLATE_content, 'modules/report/parents.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'student', 'student_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'has_boarder', 'has_boarder_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'all_year_selector', 'all_year_selector_block');
        $this->getPage()->setBlock('all_year_selector', 'all_year_option', 'all_year_option_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'search_form_option', 'search_form_option_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'show_filter_options', 'show_filter_options_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'email_all_parents', 'email_all_parents_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'email_all_parents_link', 'email_all_parents_link_block');
        $this->getPage()->setTitle('Parent Directory');
        $this->getPage()->setBlock('student', 'contact', 'contact_block');
        $this->getPage()->setBlock('contact', 'spouse', 'spouse_block');
        $this->getPage()->setBlock('contact', 'primary', 'primary_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'empty', 'empty_block');

        $this->getPage()->setBlock('student', 'public', 'public_block');
        $this->getPage()->setBlock('student', 'private', 'private_block');

        $this->getPage()->setVar(
            array(
                'has_boarder_block'            => '',
                'student_block'                => '',
                'contact_block'                => '',
                'public_block'                 => '',
                'private_block'                => '',
                'show_filter_options_block'    => '',
                'email_all_parents_block'      => '',
                'email_all_parents_link_block' => '',
            )
        );

        if (Config::get('show_boarder_block')) {
            $this->getPage()->parse('has_boarder_block', 'has_boarder');
        }

        if (Config::get('modules_parentdirectory_emailall')) {
            $this->getPage()->parse('email_all_parents_block', 'email_all_parents');
            $this->getPage()->parse('email_all_parents_link_block', 'email_all_parents_link');
        }

        if (strlen($this->getYearLevel())) {
            $heading = ': ' . $this->getYearLevelDescription($this->getYearLevel());
        } else if (strlen($this->getSearchForm())) {
            $heading = ': Form ' . $this->getSearchForm();
        } else {
            $heading = '';
        }

        $this->getPage()->setVar(
            array(
                'YEAR'        => strlen($this->getYearLevel()) ? $this->getYearLevel() : 'not selected',
                'SEARCH_NAME' => $this->getSearchName(),
                'SEARCH_FORM' => $this->getSearchForm(),
                'SEARCH_YEAR' => $this->getYearLevel(),
                'SEARCH_WIDTH' => Config::get('show_boarder_block') ? 6 : 8,
                'HEADING'     => $heading,
                'REPLY_TO'    => $_SESSION['user']->getEmail(),
            )
        );

        /* Search boarding status dropdown preselection
         */
        foreach ($this->getBoardingStatusOptions() as $currentBoardingStatus) {
            $key = 'SEARCH_BOARDINGSTATUS_' . strtoupper($currentBoardingStatus);

            if ($currentBoardingStatus == $this->getSearchBoardingStatus()) {
                $this->getPage()->setVar(
                    array(
                        $key => 'selected'
                    )
                );
            } else {
                $this->getPage()->setVar(
                    array(
                        $key => ''
                    )
                );
            }
        }

        /* Search Form dropdown preselection
         */
        $allForms = $this->getExternalUserHandler()->getStudentForms();

        if (is_array($allForms) && count($allForms)) {
            foreach ($allForms as $currentForm) {
                $this->getPage()->setVar(
                    array(
                        'SEARCHFORM_VALUE'       => $currentForm['Code'],
                        'SEARCHFORM_SELECTED'    => $currentForm['Code'] == $this->getSearchForm() ? 'selected="selected"' : '',
                        'SEARCHFORM_DESCRIPTION' => $currentForm['Description'],

                    )
                );

                $this->getPage()->parse('search_form_option_block', 'search_form_option', true);
            }
        } else {
            $this->getPage()->setVar('search_form_option_block', '');
        }

        $allYears = $this->isYearsRestricted()
            ? $this->allowedSearchableYears
            : $this->sisAllYearLevels;

        foreach ($allYears as $year) {
            $this->getPage()->setVar([
                'YEAR_CODE' => $year['Code'],
                'YEAR_DESCRIPTION' => $year['Description'],
                'YEAR_SELECTED' => $year['Code'] == $this->getYearLevel() ? 'selected="selected"' : '',
            ]);

            $this->getPage()->parse('all_year_option_block', 'all_year_option', true);
        }

        $this->getPage()->parse('all_year_selector_block', 'all_year_selector');
    }


    /**
     * Is this user restricted to accessing certain year levels?
     *
     * e.g. a parent may be restricted from viewing the details outside of
     * children outside of their children's year levels.
     *
     * @return bool
     */
    protected function isYearsRestricted()
    {
        return Config::get('restrict_parent_directory')
        && !$this->getGroupHandler()->isSuperuser()
        && !$this->getGroupHandler()->isStaff();
    }


    /**
     * Gets the year levels in which this parent has got children.
     *
     * @return array
     */
    protected function getChildrensYears()
    {
        $allYears = array();
        if (is_array($_SESSION['children'] ?? null) && count($_SESSION['children'])) {
            foreach ($_SESSION['children'] as $currentChild) {
                $student = $this->getExternalUserHandler()->getStudent($currentChild['sid']);
                $allYears[$student['StudentYearLevel']] = true;
            }
        }
        $allYears = array_keys($allYears);
        return $allYears;
    }

    /**
     * Filters all year levels based on parent's student year levels to get the year level description
     *
     * @param array $studentYearLevelsSIS (e.g. [5,6,9,12])
     * @param array $allYearLevelsSIS - two-dimentional array of all years (e.g. [['Code'] => 5, ['Description']=>'Year 5',...])
     * @return array - returns a two-dimentional array
     */
    protected function filterToStudentYearLevels(array $studentYearLevelsSIS, array $allYearLevelsSIS): array
    {
        $intersection = [];
        if (!empty($studentYearLevelsSIS)) {
            foreach ($studentYearLevelsSIS as $studentYear) {
                foreach ($allYearLevelsSIS as $yearSIS) {
                    if ($studentYear == $yearSIS['Code']) {
                        $intersection[] = $yearSIS;
                    }
                }
            }
        } else {
            $intersection = $allYearLevelsSIS;
        }
        return $intersection;
    }

    /**
     * Render data
     */
    public function render(): void
    {
        $allContacts = $this->getAllRecords();

        if ($this->isYearsRestricted()) {
            $contactsFiltered = [];
            $allYears = $this->getChildrensYears();
            foreach ($allContacts as $contact) {
                foreach ($allYears as $currentYear) {
                    if ($currentYear == Scrub::text($contact['StudentYearLevel'])) {
                        $contactsFiltered[] = $contact;
                    }
                }
            }
            $allContacts = $contactsFiltered;
        }

        $allEmails = array();

        $showContact = false;

        if (is_array($allContacts) && count($allContacts)) {
            // Show search options
            if (!strlen($this->getYearLevel()) && !$this->getSearchName() && !$this->getSearchForm() && !$this->getSearchBoardingStatus()) {
                $this->getPage()->parse('show_filter_options_block', 'show_filter_options');
            }

            $count = count($allContacts);

            for ($lastID = -1, $i = 0; $i < $count; $i++) {
                $currentContact = $allContacts[$i];

                /* New student, or first student
                 */
                if ($lastID != $currentContact['StudentID']) {
                    if ($i) {
                        if ($showContact == false) {
                            $this->getPage()->setVar(array(
                                'public_block' => ''
                            ));

                            $this->getPage()->parse('private_block', 'private');
                        } else {
                            $this->getPage()->setVar(array(
                                'private_block' => ''
                            ));

                            $this->getPage()->parse('public_block', 'public');
                        }

                        $this->getPage()->parse('student_block', 'student', true);

                        /* Clear children after parsing out the parent
                         */
                        $this->getPage()->setVar(array(
                            'public_block' => '',
                            'private_block' => '',
                        ));
                    }

                    /* Clear children blocks after parsing out the parent
                     */
                    $this->getPage()->setVar('contact_block', '');

                    $showContact = false;

                    $this->getPage()->setVar(
                        array(
                            'STUDENT_FIRSTNAME' => Scrub::text($currentContact['StudentPreferred']),
                            'STUDENT_SURNAME'   => Scrub::text($currentContact['StudentSurname']),
                            'STUDENT_YEAR'      => Scrub::text($currentContact['StudentYearLevel']),
                            'STUDENT_BOARDER'   => Scrub::text($currentContact['StudentBoarder'] == 'B' ? '(Boarder)' : ''),
                        )
                    );

                    /* Include Student House (there's also a BoardingHouse element
                     * that could also be used?)
                     */
                    if (strlen(trim($currentContact['StudentHouse']))) {
                        $this->getPage()->setVar(
                            array(
                                'STUDENT_HOUSE' => '(' . Scrub::text($currentContact['StudentHouse']) . ')'
                            )
                        );
                    } else {
                        $this->getPage()->setVar(
                            array(
                                'STUDENT_HOUSE' => ''
                            )
                        );
                    }
                }

                if ($currentContact['PrimaryDirectoryIncludeFlag'] == 1) {
                    $postalAddress = '';
                    $homeAddress = '';

                    /* Combine additional address details
                     * strlen/trim check in case of whitespace characters coming out
                     *
                     * Do it for both home and postal addresses
                     */

                    /* Postal Address
                     */
                    if (strlen(trim($currentContact['StudentContactAddress1']))) {
                        $postalAddress .= Scrub::text($currentContact['StudentContactAddress1']);
                    }

                    if (strlen(trim($currentContact['StudentContactAddress2']))) {
                        $postalAddress .= '<br />' . Scrub::text($currentContact['StudentContactAddress2']);
                    }

                    if (strlen(trim($currentContact['StudentContactAddress3']))) {
                        $postalAddress .= '<br />' . Scrub::text($currentContact['StudentContactAddress3']);
                    }

                    $postalAddress .= '<br />';
                    $postalCountry = Scrub::text($currentContact['StudentContactCountryCode']);

                    /* Home Address
                     */
                    if (strlen(trim($currentContact['StudentContactHomeAddress1']))) {
                        $homeAddress .= Scrub::text($currentContact['StudentContactHomeAddress1']);
                    }

                    if (strlen(trim($currentContact['StudentContactHomeAddress2']))) {
                        $homeAddress .= '<br />' . Scrub::text($currentContact['StudentContactHomeAddress2']);
                    }

                    if (strlen(trim($currentContact['StudentContactHomeAddress3']))) {
                        $homeAddress .= '<br />' . Scrub::text($currentContact['StudentContactHomeAddress3']);
                    }

                    $homeAddress .= '<br />';
                    $homeCountry = Scrub::text($currentContact['StudentContactHomeCountryCode']);

                    /* Figure out whether to use the Preferred or the Given (LegalFullName) field
                     * for this contact
                     */
                    if (strlen($currentContact['StudentContactPreferred'])) {
                        $firstname = $currentContact['StudentContactPreferred'];
                    } else {
                        $firstname = $currentContact['StudentContactLegalFullName'];
                    }

                    $this->getPage()->setVar(
                        array(
                            'CONTACT_CONTACT_TYPE'    => Scrub::text($currentContact['StudentContactType']),
                            'CONTACT_TITLE'           => Scrub::text($currentContact['StudentContactTitle']),
                            'CONTACT_FIRSTNAME'       => Scrub::text($firstname),
                            'CONTACT_SURNAME'         => Scrub::text($currentContact['StudentContactSurname']),
                            'CONTACT_PHONE'           => $currentContact['StudentContactSilentPhoneFlag'] ? '' : Scrub::text($currentContact['StudentContactPhoneActual']),
                            'CONTACT_MOBILE'          => $currentContact['StudentContactSilentMobilePhoneFlag'] ? '' : Scrub::text($currentContact['StudentContactMobilePhone']),
                            'CONTACT_EMAIL'           => Scrub::text($currentContact['StudentContactDefaultEmail']),

                            'CONTACT_POSTAL_STREET'   => $postalAddress,
                            'CONTACT_POSTAL_SUBURB'   => Scrub::text($currentContact['StudentContactSuburb']),
                            'CONTACT_POSTAL_STATE'    => Scrub::text($currentContact['StudentContactState']),
                            'CONTACT_POSTAL_POSTCODE' => Scrub::text($currentContact['StudentContactPostCode']),
                            'CONTACT_POSTAL_COUNTRY'  => $postalCountry,

                            'CONTACT_HOME_STREET'     => $homeAddress,
                            'CONTACT_HOME_SUBURB'     => Scrub::text($currentContact['StudentContactHomeSuburb']),
                            'CONTACT_HOME_STATE'      => Scrub::text($currentContact['StudentContactHomeState']),
                            'CONTACT_HOME_POSTCODE'   => Scrub::text($currentContact['StudentContactHomePostCode']),
                            'CONTACT_HOME_COUNTRY'    => (trim($homeCountry) == 'Not Selected' ? '' : $homeCountry),
                        )
                    );

                    $showContact = true;

                    if ($currentContact['StudentContactDefaultEmail']) {
                        $allEmails[] = $currentContact['StudentContactDefaultEmail'];
                    }

                    $this->getPage()->parse('primary_block', 'primary');
                } else {
                    /* Clear fields for spouse
                     */
                    $this->getPage()->setVar(
                        array(
                            'CONTACT_PHONE'           => '',
                            'CONTACT_MOBILE'          => '',
                            'CONTACT_POSTAL_STREET'   => '',
                            'CONTACT_POSTAL_SUBURB'   => '',
                            'CONTACT_POSTAL_STATE'    => '',
                            'CONTACT_POSTAL_POSTCODE' => '',
                            'CONTACT_POSTAL_COUNTRY'  => '',

                            'CONTACT_HOME_STREET'     => '',
                            'CONTACT_HOME_SUBURB'     => '',
                            'CONTACT_HOME_STATE'      => '',
                            'CONTACT_HOME_POSTCODE'   => '',
                            'CONTACT_HOME_COUNTRY'    => '',
                            'CONTACT_EMAIL'           => '',
                        )
                    );

                    $this->getPage()->setVar('primary_block', '');
                }

                if ((($currentContact['StudentContactSpousePreferred'] != '') || ($currentContact['StudentContactSpouseLegalFullName'] != '')) && ($currentContact['SpouseDirectoryIncludeFlag'] == 1)) {
                    /* Figure out whether to use the Preferred or the Given (LegalFullName) field
                     * for this contact
                     */
                    if (strlen($currentContact['StudentContactSpousePreferred'])) {
                        $firstname = $currentContact['StudentContactSpousePreferred'];
                    } else {
                        $firstname = $currentContact['StudentContactSpouseLegalFullName'];
                    }

                    $this->getPage()->setVar(
                        array(
                            'SPOUSE_CONTACT_TYPE'   => Scrub::text($currentContact['StudentContactSpouseType'] ?? ''),
                            'SPOUSE_TITLE'          => Scrub::text($currentContact['StudentContactSpouseTitle']),
                            'SPOUSE_FIRSTNAME'      => Scrub::text($firstname),
                            'SPOUSE_SURNAME'        => Scrub::text($currentContact['StudentContactSpouseSurname']),
                            'SPOUSE_CONTACT_MOBILE' => Scrub::text($currentContact['StudentContactSpouseMobilePhone']),
                            'SPOUSE_CONTACT_EMAIL'  => Scrub::text($currentContact['StudentContactSpouseDefaultEmail']),
                        )
                    );

                    $showContact = true;

                    if ($currentContact['StudentContactDefaultEmail']) {
                        $allEmails[] = $currentContact['StudentContactSpouseDefaultEmail'];
                    }

                    $this->getPage()->parse('spouse_block', 'spouse');
                } else {
                    $this->getPage()->setVar('spouse_block', '');
                }

                if ($showContact === true) {
                    $this->getPage()->parse('contact_block', 'contact', true);
                } else {
                    $this->getPage()->setVar('contact_block', '');
                }

                $lastID = $currentContact['StudentID'];
            }

            /* Last parse for final element
             */
            if ($showContact == false) {
                $this->getPage()->setVar(
                    array(
                        'PRIVATE' => 'private'
                    )
                );
            } else {
                $this->getPage()->setVar(
                    array(
                        'PRIVATE' => ''
                    )
                );
            }

            // Uniqueify and cleanup empty string emails
            $filteredEmails = array_filter(array_unique($allEmails));

            $this->getPage()->parse('student_block', 'student', true);
            $this->getPage()->setVar('EMAIL_ALL_PARENTS', implode(',', $filteredEmails));
            $this->getPage()->setVar('empty_block', '');
        } else {
            $this->getPage()->parse('show_filter_options_block', 'show_filter_options');
            if (isset($_GET["searchName"])) {
                $this->getPage()->parse('empty_block', 'empty', true);
            } else {
                $this->getPage()->setVar('empty_block', '');
            }
        }
    }

    /**
     * Export parent list
     *
     * @param Report_Exporter $exporter
     * @param string $format
     */
    public function export(Report_Exporter $exporter, $format = 'csv')
    {
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
    protected function setSearchBoardingStatus($searchBoardingStatus)
    {
        $this->searchBoardingStatus = $searchBoardingStatus;
    }

    /**
     * Get search boarding status
     *
     * @return boolean
     */
    protected function getSearchBoardingStatus()
    {
        return $this->searchBoardingStatus;
    }

    /**
     * Set search form
     *
     * @param string $searchForm
     */
    protected function setSearchForm($searchForm)
    {
        $this->searchForm = $searchForm;
    }

    /**
     * Get search form
     *
     * @return string
     */
    protected function getSearchForm()
    {
        return $this->searchForm;
    }

    /**
     * Set search name
     *
     * @param string $searchName
     */
    protected function setSearchName($searchName)
    {
        $this->searchName = $searchName;
    }

    /**
     * Get search name
     *
     * @return string
     */
    protected function getSearchName()
    {
        return $this->searchName;
    }

    /**
     * Set boarding status options
     *
     * @param array $boardingStatusOptions
     */
    protected function setBoardingStatusOptions($boardingStatusOptions)
    {
        $this->boardingStatusOptions = $boardingStatusOptions;
    }

    /**
     * Get boarding status options
     *
     * @return array
     */
    protected function getBoardingStatusOptions()
    {
        return $this->boardingStatusOptions;
    }

    /**
     * @param string $yearLevelCode
     * @return string|null
     */
    protected function getYearLevelDescription(string $yearLevelCode): ?string
    {
        foreach ($this->allowedSearchableYears as $year) {
            if ($yearLevelCode == $year['Code']) {
                return $year['Description'];
            }
        }
        return null;
    }
}
