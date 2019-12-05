<?php
/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/** @var SitePage $this */
$this->setFile(TEMPLATE_content, 'dashboard/index.html');
$this->setTab('main');

/* Instance factory */
$groupID = $_SESSION['gid'];

$componentFactory = new Component_Dashboard_Factory($this->getDi()->get('session')->get('user'), $this);
$componentCollection = $componentFactory->buildComponentList($groupID);

$occupiedPositionList = array();

foreach ($componentCollection as $component) {
    try {
        /** @var Component_Dashboard_ControllerAbstract $component */
        $instance = $component->getInstance();
        $templateTarget = "component_position_{$instance->getPosition()}";
        $templateHandle = "component_{$instance->getId()}";

        $component->setup();

        if ($component->execute()) {
            $component->parseConfig();

            if ($component->renderTemplate($templateHandle) !== false) {
                if (!in_array($instance->getPosition(), $occupiedPositionList)) {
                    $occupiedPositionList[] = $instance->getPosition();
                }

                /* Admin configuration and index.php both use the same template.
                 * The former uses the template to configure; but the latter doesn't
                 * even display the title bar (which is now part of wrappers).
                 */
                $this->setVar('CONFIGURATION', '');
                $this->parse($templateTarget, $templateHandle, true);
            }
        }
    } catch (\Throwable $e) {
        // if a component explodes for any reason, we only want it to explode the page if we're in debug mode
        if (Config::get('debug_mode')) {
            throw $e;
        }

        if ($this->getDi()->has('sentry')) {
            /** @var Raven_Client $sentry */
            $sentry = $this->getDi()->get('sentry');
            $sentry->captureException($e);
        }
    }
}

$emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);

foreach ($emptyPositionList as $emptyPosition) {
    $this->setVar("component_position_{$emptyPosition}", '');
}
