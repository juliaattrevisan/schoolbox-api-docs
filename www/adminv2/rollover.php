<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Web\Template\DebugTwig;
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

$nav = new AdminV2_Nav($this->getDi()->get('config'));
$nav->parse($this, 'rollover');

$openCategory = 0;

// shamelessly copied from resources.php
$treeBuilder = new \Schoolbox\Resource\Browser\Resources\TreeBuilder(
    Folder\Mapper::getInstance(),
    $_SESSION['user'],
    $this->getLocalisedTerm('resource', true, true)
);
/** @var Alaress\Web\Output\Html $twigRenderer */
$twigRenderer = $this->getDi()->get('webOutput:nochrome');
$this->setVar('JS_NODE_TYPE_ICON_MAP', $twigRenderer->setData([DebugTwig::DEBUG_WRAP => false])->setTemplate('@resourceBrowser/jsNodeIconMap.twig')->getAsString());
$this->setVar('TREEDATA', json_encode($treeBuilder->buildPartialTree($openCategory)));
