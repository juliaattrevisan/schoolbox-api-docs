<?php

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$categoryID = $_POST['cid'];
$definitionID = $_POST['add'];
$instanceID = Database::getInstance()->lastInsertId();

// check perms
$groupHandler = new Group(HOMEPAGE_SERVICE);
if (($permission = $groupHandler->getPermission($categoryID)) < WRITE_ACCESS) {
    exit;
}

// get the Instance's Definition's Controller
$factory = new Component_Homepage_Factory(new User(), new SitePage(false));
$component = $factory->buildComponent($instanceID);

$templateTarget = "component_position_{$component->getInstance()->getPosition()}";
$templateHandle = "component_{$component->getInstance()->getId()}";
$templateHandle = TEMPLATE_content;

$component->setup(array('id' => $categoryID), $component->getInstance()->getConfigArray());

if ($component->execute() !== false) {
    if ($component->renderTemplate($templateHandle) !== false) {
        $component->parse($templateTarget, $templateHandle, true);
    }
}

return;
