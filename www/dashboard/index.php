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

$componentFactory = new Component_Dashboard_Factory(new User, $this);
$componentCollection = $componentFactory->buildComponentList($groupID);

$occupiedPositionList = array();

foreach ($componentCollection as $component) {
    $templateTarget = "component_position_{$component->position}";
    $templateHandle = "component_{$component->id}";

    if ($component->execute() !== false) {

        $component->setup(array(), array());
        $component->parseConfig();

        if ($component->renderTemplate($templateHandle) !== false) {
            if (!in_array($component->instance->position, $occupiedPositionList)) {
                $occupiedPositionList[] = $component->instance->position;
            }

            $this->parse($templateTarget, $templateHandle, true);
        }
    }
}

$emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);

foreach ($emptyPositionList as $emptyPosition) {
    $this->setVar("component_position_{$emptyPosition}", '');
}
