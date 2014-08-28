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
        'SEARCH_CAMPUS_NAME' => $currentCampus->getName()
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
        'SEARCH_ROLE_NAME' => $currentRole->getName()
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
        'key' => 0,
        'label' => 'Enabled'
    ),
    array(
        'key' => 1,
        'label' => 'Disabled'
    )
));

foreach($allEportfolio as $currentEportfolio) {
    $this->setVar(array(
        'SEARCH_EPORTFOLIO_ID' => $currentEportfolio['key'],
        'SEARCH_EPORTFOLIO_NAME' => $currentEportfolio['label'],
    ));

    $this->parse('search_eportfolio_block', 'search_eportfolio', true);

    $this->setVar(array(
        'APPLY_EPORTFOLIO_ID' => $currentEportfolio['key'],
        'APPLY_EPORTFOLIO_NAME' => $currentEportfolio['label'],
    ));

    $this->parse('apply_eportfolio_block', 'apply_eportfolio', true);
}

/* If there were any search results, display control options
 */
if ($searchResults) {
    $this->parse('has_results_block', 'has_results');
}
else {
    $this->setVar(array(
        'has_results_block' => ''
    ));
}