<?php
/**
 *
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 *
 */
$this->setFile(TEMPLATE_content, 'dashboard/index.html');
$this->setTab('main');

/* Instance factory */
$groupID = $_SESSION['gid'];

$componentFactory = new Component_Dashboard_Factory(new \Schoolbox\User(), $this);
$componentCollection = $componentFactory->buildComponentList($groupID);

$occupiedPositionList = array();

foreach ($componentCollection as $component) {
    /** @var Component_Homepage_ControllerAbstract $component */
    $instance = $component->getInstance();
    $templateTarget = "component_position_{$instance->getPosition()}";
    $templateHandle = "component_{$instance->getDefinitionId()}";

    if ($component->execute() !== false) {

        $component->setup(array(), array());
        $component->parseConfig();

        if ($component->renderTemplate($templateHandle) !== false) {
            if (!in_array($instance->getPosition(), $occupiedPositionList)) {
                $occupiedPositionList[] = $instance->getPosition();
            }

            $this->parse($templateTarget, $templateHandle, true);
        }
    }
}

$emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);

foreach ($emptyPositionList as $emptyPosition) {
    $this->setVar("component_position_{$emptyPosition}", '');
}
