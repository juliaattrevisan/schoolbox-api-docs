<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if(isset($_GET['date_start']) && isset($_GET['date_end']) && isset($_GET['drop'])) {
    $this->localRedir('/'. implode('/', $_GET));
}


$groupID = $_SESSION['gid'];
$catHandler = new Category();

if (preg_match('/^[1-9]+[0-9]*$/', $this->arg[0])) {
    $category = $catHandler->getByID($this->arg[0]);
}
else {
    $category = null;
}

if (!is_array($category)) {
    $code = implode('/', $this->arg);
    $category = $catHandler->getByCode(urldecode(urldecode($code)));
}

if (!isset($category['id'])) {
    error(18);
}

$categoryID = $category['id'];

// hack away at the args provided
if(!(isset($this->arg[1]) && preg_match('#[0-9]+-[0-9]+#', $this->arg[1]))) {
    $this->arg[1] = date('Y-m');
}

$homepageHandler = new Homepage($categoryID);

$g = new Group();
$permission = $g->getPermission($categoryID);

$this->setTab('resources');
$this->setTitle($category['name'] . ' Statistics');
$this->setFile(TEMPLATE_content, 'statistics/homepage.html');

$this->setVar('CATEGORY_NAME', $category['name']);
$this->setVar('ID', $categoryID);

/* PARSE COMPONENTRY */
$componentFactory = new Component_Homepage_Statistics_Factory(new User, $this);
$componentCollection = $componentFactory->buildComponentList($categoryID);
$occupiedPositionList = array();
$j = 0;
/** @var Component_Homepage_Statistics_ControllerAbstract[] $componentCollection */
foreach ($componentCollection as $component) {
    $templateTarget = "component_position_{$component->getInstance()->getPosition()}";
    $templateHandle = 'component_'.$j++;

    $component->setup($category, $component->getInstance()->getConfigArray());

    if ($component->execute() !== false) {
        if ($component->renderTemplate($templateHandle) !== false) {
            if (!in_array($component->getInstance()->getPosition(), $occupiedPositionList)) {
                $occupiedPositionList[] = $component->getInstance()->getPosition();
            }

            $this->parse($templateTarget, $templateHandle, true);
        }
    }
}

$emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);

foreach ($emptyPositionList as $emptyPosition) {
    $this->setVar("component_position_{$emptyPosition}", '');
}
