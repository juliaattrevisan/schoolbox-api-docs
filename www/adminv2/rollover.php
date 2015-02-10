<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$group = new Group();

if ( !$group->isSuperuser() ) {
	error(19);
}

/**
 * @var $this SitePage
 */

$this->setFile(TEMPLATE_content, 'adminv2/rollover.html');

$nav = new AdminV2_Nav();
$nav->parse($this, 'rollover');

$groupHandler = new Group();

$openCategory = 0;

// shamelessly copied from resources.php
$treeBuilder = new Schoolbox\Resource\CategoryTreeBuilder(
    Schoolbox\Resource\CategoryMapper::getInstance(),
    Schoolbox\Assessment\Course\TaskMapper::getInstance(),
    $groupHandler
);
$this->setVar('TREEDATA', json_encode($treeBuilder->buildPartialTree($openCategory)));
