<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/* Superusers only
 */

use Schoolbox\Error\Mapper as ErrorMapper;

if (!$this->getDi()->get('session')->get('user')->isSuperuser()) {
    error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

$this->setFile(TEMPLATE_content, 'adminv2/user/bulk/modify.html');

/* Search options
 */
$this->setBlock(TEMPLATE_content, 'search_campus', 'search_campus_block');
$this->setBlock(TEMPLATE_content, 'search_role', 'search_role_block');
$this->setBlock(TEMPLATE_content, 'search_eportfolio', 'search_eportfolio_block');

$this->setBlock(TEMPLATE_content, 'has_results', 'has_results_block');
$this->setBlock('has_results', 'user', 'user_block');
$this->setBlock('has_results', 'apply_campus', 'apply_campus_block');
$this->setBlock('has_results', 'apply_role', 'apply_role_block');
$this->setBlock('has_results', 'apply_eportfolio', 'apply_eportfolio_block');
$this->setBlock('has_results', 'apply_webmail', 'apply_webmail_block');
$this->setBlock('has_results', 'apply_accountenabled', 'apply_accountenabled_block');
$this->setBlock('has_results', 'apply_hidecontacts', 'apply_hidecontacts_block');
$this->setBlock('has_results', 'apply_hidetimetable', 'apply_hidetimetable_block');
$this->setBlock('has_results', 'apply_emailfromusername', 'apply_emailfromusername_block');
$this->setBlock('has_results', 'apply_externalmailclient', 'apply_externalmailclient_block');

/* Campus Search options
 */
$campusMapper = new \Schoolbox\CampusMapper();
$allCampuses = $campusMapper->findAll();

foreach ($allCampuses as $currentCampus) {
    $this->setVar(
        array(
            'SEARCH_CAMPUS_ID' => $currentCampus->getId(),
            'SEARCH_CAMPUS_NAME' => $currentCampus->getName(),
            'SEARCH_CAMPUS_SELECTED' => ($currentCampus->getId() == $_POST['search_campus']) && isset($_POST['search_campus']) ? 'selected="selected"' : '',
        )
    );

    $this->parse('search_campus_block', 'search_campus', true);

    /* New Campus setting
     */
    $this->setVar(
        array(
            'APPLY_CAMPUS_ID' => $currentCampus->getId(),
            'APPLY_CAMPUS_NAME' => $currentCampus->getName()
        )
    );

    $this->parse('apply_campus_block', 'apply_campus', true);
}

/* Group Search options
 */
$roleMapper = new \Schoolbox\RoleMapper();
$allRoles = $roleMapper->findAll();

foreach ($allRoles as $currentRole) {
    $this->setVar(
        array(
            'SEARCH_ROLE_ID' => $currentRole->getId(),
            'SEARCH_ROLE_NAME' => $currentRole->getName(),
            'SEARCH_ROLE_SELECTED' => ($currentRole->getId(
                    ) == $_POST['search_role']) && isset($_POST['search_role']) ? 'selected="selected"' : '',
        )
    );

    $this->parse('search_role_block', 'search_role', true);

    $this->setVar(
        array(
            'APPLY_ROLE_ID' => $currentRole->getId(),
            'APPLY_ROLE_NAME' => $currentRole->getName()
        )
    );

    $this->parse('apply_role_block', 'apply_role', true);
}

/* Year
 */
$this->setVar(
    array(
        'SEARCH_YEAR_SELECTED' => Scrub::html($_POST['search_year']),
    )
);

/* Boolean options
 */
$allBoolean = new \Schoolbox\Collection(array(
    array(
        'key' => 1,
        'label' => 'Enabled'
    ),
    array(
        'key' => 0,
        'label' => 'Disabled'
    )
));

/* A whole bunch of boolean options
 */
foreach ($allBoolean as $currentEportfolio) {

    $this->setVar(
        array(
            'SEARCH_EPORTFOLIO_ID' => $currentEportfolio['key'],
            'SEARCH_EPORTFOLIO_NAME' => $currentEportfolio['label'],
            'SEARCH_EPORTFOLIO_SELECTED' => ($currentEportfolio['key'] == $_POST['search_eportfolio']) && isset($_POST['search_eportfolio']) ? 'selected="selected"' : '',
        )
    );

    $this->parse('search_eportfolio_block', 'search_eportfolio', true);

    $this->setVar(
        array(
            'APPLY_EPORTFOLIO_ID' => $currentEportfolio['key'],
            'APPLY_EPORTFOLIO_NAME' => $currentEportfolio['label'],
        )
    );

    $this->parse('apply_eportfolio_block', 'apply_eportfolio', true);
}

/* Modify-only booleans
 */
foreach ($allBoolean as $currentEmailFromUsername) {
    $this->setVar(
        array(
            'APPLY_EMAILFROMUSERNAME_ID' => $currentEmailFromUsername['key'],
            'APPLY_EMAILFROMUSERNAME_NAME' => $currentEmailFromUsername['label'],
        )
    );

    $this->parse('apply_emailfromusername_block', 'apply_emailfromusername', true);
}

foreach ($allBoolean as $currentExternalMailClient) {
    $this->setVar(
        array(
            'APPLY_EXTERNALMAILCLIENT_ID' => $currentExternalMailClient['key'],
            'APPLY_EXTERNALMAILCLIENT_NAME' => $currentExternalMailClient['label'],
        )
    );

    $this->parse('apply_externalmailclient_block', 'apply_externalmailclient', true);
}

foreach ($allBoolean as $currentWebmail) {
    $this->setVar(
        array(
            'APPLY_WEBMAIL_ID' => $currentWebmail['key'],
            'APPLY_WEBMAIL_NAME' => $currentWebmail['label'],
        )
    );

    $this->parse('apply_webmail_block', 'apply_webmail', true);
}

foreach ($allBoolean as $currentAccountEnabled) {
    $this->setVar(
        array(
            'APPLY_ACCOUNTENABLED_ID' => $currentAccountEnabled['key'],
            'APPLY_ACCOUNTENABLED_NAME' => $currentAccountEnabled['label'],
        )
    );

    $this->parse('apply_accountenabled_block', 'apply_accountenabled', true);
}

foreach ($allBoolean as $currentHideContacts) {
    $this->setVar(
        array(
            'APPLY_HIDECONTACTS_ID' => $currentHideContacts['key'],
            'APPLY_HIDECONTACTS_NAME' => $currentHideContacts['label'],
        )
    );

    $this->parse('apply_hidecontacts_block', 'apply_hidecontacts', true);
}

foreach ($allBoolean as $currentHideTimetable) {
    $this->setVar(
        array(
            'APPLY_HIDETIMETABLE_ID' => $currentHideTimetable['key'],
            'APPLY_HIDETIMETABLE_NAME' => $currentHideTimetable['label'],
        )
    );

    $this->parse('apply_hidetimetable_block', 'apply_hidetimetable', true);
}

/* If there were any search results, display control options
 */
if (!empty($_POST)) {
    $userMapper = \Schoolbox\UserBulkMapper::getInstance();

    $allUsers = $userMapper->bulkModifySearch(
        $_POST['search_campus'],
        $_POST['search_role'],
        $_POST['search_eportfolio'],
        $_POST['search_year']
    );

    $this->setVar(array(
        'NUM_FOUND' => $allUsers->count()
    ));

    if ($allUsers->count()) {
        foreach ($allUsers as $currentUser) {
            /** @var \Schoolbox\User $currentUser */
            $campusNames = array();

            foreach ($currentUser->getCampusCollection() as $currentCampus) {
                $campusNames[] = $currentCampus->getName();
            }

            $this->setVar(
                array(
                    'BULK_USER_ID' => $currentUser->getId(),
                    'BULK_USER_FIRSTNAME' => $currentUser->getFirstname(),
                    'BULK_USER_LASTNAME' => $currentUser->getLastname(),
                    'BULK_USER_EXTERNAL_ID' => $currentUser->getExternalId(),
                    'BULK_USER_CAMPUS' => implode(', ', $campusNames),
                    'BULK_USER_GROUP' => $currentUser->getRole()->getName(),
                    'BULK_USER_YEAR' => $currentUser->getYear(),
                )
            );

            $this->parse('user_block', 'user', true);
        }
    } else {
        $this->setVar(
            array(
                'user_block' => ''
            )
        );
    }

    $this->parse('has_results_block', 'has_results');
} else {
    $this->setVar(
        array(
            'has_results_block' => ''
        )
    );
}
