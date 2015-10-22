<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

if (isset($_GET['date_start']) && isset($_GET['date_end']) && isset($_GET['drop'])) {
    $this->localRedir('/'. implode('/', $_GET));
}

$groupID = $_SESSION['gid'];

$folderMapper = new \Schoolbox\Resource\CategoryMapper();

$folder = new \Schoolbox\Resource\Category();

/* Try to find by folder id first
 */
if (preg_match('/^[1-9]+[0-9]*$/', $this->arg[0])) {
    try {
        $folder = $folderMapper->findById($this->arg[0]);
    } catch (\Exception $e) {
    }
}

/* If you couldn't find it by id, try by category code
 */
if (!is_numeric($folder->getId())) {
    $code = implode('/', $this->arg);

    try {
        $folder = $folderMapper->findByCode(urldecode(urldecode($code)));
    } catch (\Exception $e) {
/* STILL couldn't find it? die
         */
        error(18);
    }
}

// hack away at the args provided
if (!(isset($this->arg[1]) && preg_match('#[0-9]+-[0-9]+#', $this->arg[1]))) {
    $this->arg[1] = date('Y-m');
}

$homepageHandler = new Homepage($folder->getId());

$g = new Group();
$permission = $g->getPermission($folder->getId());

/* Only write-access people can view statistics
 */
if ($permission < WRITE_ACCESS) {
    error(19);
}

$this->setTab('resources');
$this->setTitle($folder->getName() . ' Statistics');
$this->setFile(TEMPLATE_content, 'statistics/homepage.html');

$this->setVar('CATEGORY_NAME', $folder->getName());
$this->setVar('ID', $folder->getId());

/* Render out breadcrumbs
     */
$trail = new \Schoolbox\Breadcrumbs();
$trail->renderIntoSitePage($this, 'BREADCRUMB', [
    [
        'text' => $folder->getName(),
        'href' => "/homepage/{$folder->getId()}",
        'class' => '',
    ],
    [
        'text' => 'Statistics',
        'href' => '',
        'class' => 'active',
    ]
]);

/* PARSE COMPONENTRY */
$componentFactory = new Component_Homepage_Statistics_Factory(new \Schoolbox\User(), $this);
$componentCollection = $componentFactory->buildComponentList($folder->getId());
$occupiedPositionList = array();
$j = 0;
/** @var Component_Homepage_Statistics_ControllerAbstract[] $componentCollection */
foreach ($componentCollection as $component) {
    $templateTarget = "component_position_{$component->getInstance()->getPosition()}";
    $templateHandle = 'component_'.$j++;

    /* omfg you have got to be kidding
     */
    $component->setup($folder, $component->getInstance()->getConfigArray());

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
