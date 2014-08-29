<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
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

/* Campus Search options
 */
$campusMapper = new \Schoolbox\CampusMapper();
$allCampuses = $campusMapper->findAll();

foreach($allCampuses as $currentCampus) {
    $this->setVar(array(
        'SEARCH_CAMPUS_ID' => $currentCampus->getId(),
        'SEARCH_CAMPUS_NAME' => $currentCampus->getName(),
        'SEARCH_CAMPUS_SELECTED' => ($currentCampus->getId() == $_POST['search_campus']) ? 'selected="selected"' : '',
    ));

    $this->parse('search_campus_block', 'search_campus', true);

    $this->setVar(array(
        'APPLY_CAMPUS_ID' => $currentCampus->getId(),
        'APPLY_CAMPUS_NAME' => $currentCampus->getName()
    ));

    $this->parse('apply_campus_block', 'apply_campus', true);
}

/* Group Search options
 */
$roleMapper = new \Schoolbox\RoleMapper();
$allRoles = $roleMapper->findAll();

foreach($allRoles as $currentRole) {
    $this->setVar(array(
        'SEARCH_ROLE_ID' => $currentRole->getId(),
        'SEARCH_ROLE_NAME' => $currentRole->getName(),
        'SEARCH_ROLE_SELECTED' => ($currentRole->getId() == $_POST['search_role']) ? 'selected="selected"' : '',
    ));

    $this->parse('search_role_block', 'search_role', true);

    $this->setVar(array(
        'APPLY_ROLE_ID' => $currentRole->getId(),
        'APPLY_ROLE_NAME' => $currentRole->getName()
    ));

    $this->parse('apply_role_block', 'apply_role', true);
}

/* ePortfolio options
 */
$allEportfolio = new \Schoolbox\Collection(array(
    array(
        'key' => 1,
        'label' => 'Enabled'
    ),
    array(
        'key' => 0,
        'label' => 'Disabled'
    )
));

foreach($allEportfolio as $currentEportfolio) {
    $this->setVar(array(
        'SEARCH_EPORTFOLIO_ID' => $currentEportfolio['key'],
        'SEARCH_EPORTFOLIO_NAME' => $currentEportfolio['label'],
        'SEARCH_EPORTFOLIO_SELECTED' => ($currentEportfolio['key'] == $_POST['search_eportfolio']) ? 'selected="selected"' : '',
    ));

    $this->parse('search_eportfolio_block', 'search_eportfolio', true);

    $this->setVar(array(
        'APPLY_EPORTFOLIO_ID' => $currentEportfolio['key'],
        'APPLY_EPORTFOLIO_NAME' => $currentEportfolio['label'],
    ));

    $this->parse('apply_eportfolio_block', 'apply_eportfolio', true);
}

$this->setVar(array(
    'SEARCH_YEAR_SELECTED' => $_POST['searchYear']
));

/* If there were any search results, display control options
 */
if (!empty($_POST)) {
    $userMapper = new \Schoolbox\UserMapper();

    $allUsers = $userMapper->bulkModifySearch($_POST['search_campus'], $_POST['search_role'], $_POST['search_eportfolio'], $_POST['search_year']);

    if ($allUsers->count()) {
        foreach ($allUsers as $currentUser) {
            $this->setVar(array(
                'USER_ID' => $currentUser->getId(),
                'USER_FIRSTNAME' => $currentUser->getFirstname(),
                'USER_LASTNAME' => $currentUser->getLastname()
            ));

            $this->parse('user_block', 'user', true);
        }
    }
    else {
        $this->setVar(array(
            'user_block' => ''
        ));
    }

    $this->parse('has_results_block', 'has_results');
}
else {
    $this->setVar(array(
        'has_results_block' => ''
    ));
}
