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
}