<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */


use Schoolbox\Assessment\Component\SubmissionBox\Rubric\CriterionMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\Criterion;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\UsageMapper;
use Schoolbox\Resource\CategoryMapper;

// not in SitePage context, redirect to equivalent
if(!isset($this)) {
    require_once('../../include/Bootstrap.php');
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

$groupHandler = new Group();
if(!($groupHandler->isSuperuser() || $groupHandler->getPermission($catID) >= WRITE_ACCESS)) {
    error(6);
}



$this->setFile(TEMPLATE_content, 'submission/rubricCriteria.html');
$this->setTab('submission');
$this->setBlock(TEMPLATE_content, 'allow_cascade', 'allow_cascade_block');
$this->setBlock(TEMPLATE_content, 'criterion_group', 'criterion_group_block');
$this->setBlock('criterion_group', 'criterion', 'criterion_block');
$this->setBlock('criterion', 'criterion_disabled', 'criterion_disabled_block');
$this->setBlock('criterion', 'criterion_enabled', 'criterion_enabled_block');


// get category details
$category = CategoryMapper::getInstance()->findById($catID);

$this->setVar(array(
    'CATEGORY_ID' => $category->getId(),
    'CATEGORY_NAME' => Scrub::text($category->getName()),
));

$title = 'Manage Criteria for '.Scrub::text($category->getName());
$this->setTitle($title);
$this->setVar('PAGE_TITLE', $title);

// get rubric details
$criterionCollection = CriterionMapper::getInstance()->findByColumnList(
                                               array(
                                                     'parent_id'   => $catID,
                                                     'parent_type' => Criterion::PARENT_TYPE_CATEGORY,
                                               ),
                                               array('order' => array('grouping'=>'ASC', 'name'=>'ASC'))
);


// for now, we're not supporting inheritence
$this->setVar('allow_cascade_block', '');

$this->setVar('criterion_group_block', '');
$topID = 0;
if(count($criterionCollection)) {

    /* Create group structure */
    foreach($criterionCollection as $crit) {
        $criteriaList[$crit->getGrouping()][] = $crit;
    }

    $groupCount = 0;
    foreach ($criteriaList as $grouping => $crits) {
        $this->setVar(array(
            'criterion_block' => '',
            'CRITGROUP_COUNT' => $groupCount++,
            'CRITGROUP_NAME'  => $grouping,
            'CRITGROUP_DISABLED' => ''
        ));
        foreach($crits as $crit) {
            /* @var $crit Criterion */
            $topID = max($topID, $crit->getID());
            $this->setVar(array(
                'CRITERION_ID'   => $crit->getID(),
                'CRITERION_NAME' => $crit->getName(),
            ));

            $usage = UsageMapper::getInstance()->findByColumnList(array('criterion_id' => $crit->getID()));
            $usageCount = count($usage);
            if($usageCount > 0) {
                $this->setVar('CRITERION_USAGE', $usageCount);
                $this->setVar('criterion_enabled_block', '');
                $this->parse('criterion_disabled_block', 'criterion_disabled');


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
$this->setVar('MAX_CRIT_ID', $topID + 1);

$trail = new \Schoolbox\Breadcrumbs(Schoolbox\Resource\Folder\Mapper::getInstance());
$trail->renderIntoSitePage(
    $this,
    'BREADCRUMB',
    [
        [
            'text'  => $category->getName(),
            'href'  => "/homepage/{$category->getId()}",
            'class' => '',
        ],
        [
            'text'  => 'Manage Criteria',
            'href'  => '',
            'class' => 'active',
        ]
    ]
);
