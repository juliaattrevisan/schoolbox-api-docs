<?php
/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 */

use Schoolbox\Config\ConfigMapper;
use Schoolbox\User;

/** @var SitePage $this */
$this->setFile(TEMPLATE_content, 'dashboard/index.html');
$this->setTab('main');

/* Instance factory */
$groupID = $_SESSION['gid'];

$componentFactory = new Component_Dashboard_Factory($this->getDi()->get('session')->get('user'), $this);
$componentCollection = $componentFactory->buildComponentList($groupID);

$occupiedPositionList = array();

foreach ($componentCollection as $component) {
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
}

$emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);

foreach ($emptyPositionList as $emptyPosition) {
    $this->setVar("component_position_{$emptyPosition}", '');
}

// Marketo callback for Schoolbox Help

/** @var User $user */
$user = $_SESSION["user"];

/** @var ConfigMapper $configMapper */
$configMapper = ConfigMapper::getInstance();

if ($configMapper->get('site_location') == 'help') {

    // Retrieve private key from database

    $marketoKey = $configMapper->get('marketo_key');

    // Find an email address for the user...
    //
    //  - Try using $user->getEmail()
    //  - Try using $user->getAltEmail()

    if (!empty($user->getEmail())) {
        $email = $user->getEmail();
    } elseif (!empty($user->getAltEmail())) {
        $email = $user->getAltEmail();
    } else {
        $email = '';
    }

    // Create hash

    $marketoClientId = $configMapper->get('marketo_client_id');
    $marketoHash = sha1($marketoKey . $user->getEmail());

    // Pass vars to template

    $this->setVar('marketoClientId', $marketoClientId);
    $this->setVar('marketoHash', $marketoHash);
    $this->setVar('email', $email);
    $this->setVar('firstname', $user->getLastname());
    $this->setVar('lastname', $user->getFirstname());
}