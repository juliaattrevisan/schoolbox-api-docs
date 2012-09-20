<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// not in SitePage context, redirect to equivalent
if(!isset($this)) {
    require_once('../../include/SitePage.php');
    SitePage::localRedir('/submission/rubricCriteria');
    die();
}

// retrieve the categoryID or die
$catID = 0;
if(isset($this->arg[0]) && is_numeric($this->arg[0])) {
    $catID = $this->arg[0];
}
else {
    error(2);
}

$title = 'Manage Rubric Criteria';

$this->setFile(TEMPLATE_content, 'submission/rubricCriteria.html');
$this->setTab('submission');
$this->setTitle($title);
$this->setVar('PAGE_TITLE', $title);
$this->setBlock(TEMPLATE_content, 'criterion_group', 'criterion_group_block');
$this->setBlock('criterion_group', 'criterion', 'criterion_block');
$this->setBlock('criterion', 'criterion_disabled', 'criterion_disabled_block');
$this->setBlock('criterion', 'criterion_enabled', 'criterion_enabled_block');


// get category details
$catHandler = new Category();
$category = $catHandler->getByID($catID);

$this->setVar(array(
    'CATEGORY_ID' => $category['id'],
    'CATEGORY_NAME' => $category['name']
));

$rubricHandler = new DueWork_Rubric_Handler();
$criteria = $rubricHandler->getAvailableCriteria($catID, 'category', true);

if(count($criteria)) {
    $this->setVar('criterion_group_block', '');
    foreach ($criteria as $grouping => $crits) {
        $this->setVar(array(
            'criterion_block' => '',
            'CRITGROUP_NAME'  => $grouping
        ));
        foreach($crits as $critID => $crit) {

            $this->setVar(array(
                'CRITERION_ID'   => $critID,
                'CRITERION_NAME' => $crit['name'],
            ));


            if($crit['count']) {
                $usage = $crit['count'] == 1 ? 'once' : $crit['count'] . 's';
                $this->setVar(array(
                    'CRITERION_USAGE' => $usage,
                    'criterion_enabled_block' => ''
                ));
                $this->parse('criterion_disabled_block','criterion_disabled');
            }
            else {
                $this->setVar('criterion_disabled_block', '');
                $this->parse('criterion_enabled_block', 'criterion_enabled');
            }


            $this->parse('criterion_block', 'criterion', true);
        }

        $this->parse('criterion_group_block', 'criterion_group', true);
    }

}