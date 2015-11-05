<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Assessment\Course\TaskMapper;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder;

$group = new Group();

if (!$group->isSuperuser()) {
    error(19);
}

/**
 * @var $this SitePage
 */

$this->setFile(TEMPLATE_content, 'adminv2/rollover.html');

$nav = new AdminV2_Nav();
$nav->parse($this, 'rollover');

$openCategory = 0;

// shamelessly copied from resources.php
$treeBuilder = new \Schoolbox\Resource\Browser\Resources\TreeBuilder(
    Folder\Mapper::getInstance(),
    TaskMapper::getInstance(),
    UserAccessMapper::getInstance(),
    RoleAccessMapper::getInstance(),
    $_SESSION['user'],
    $this->getLocalisedTerm('resource', true, true)
);
$this->setVar('TREEDATA', json_encode($treeBuilder->buildPartialTree($openCategory)));
