<?php

// require no sitepage context
if (isset($this)) {
    // vicious lies
    $this->pageNotFound();
}

require_once(dirname(__FILE__) . '/../../include/Bootstrap.php');

$page         = new SitePage();
$groupHandler = new Group();

if (isset($_POST['textBoxHeading']) && is_numeric($_POST['categoryID'])) {
    if ($groupHandler->getPermission($_POST['categoryID']) < WRITE_ACCESS) {
        error(19);
    }

    $textbox = new \Schoolbox\Textbox\Model();

    $now = new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U');
    $textbox
        ->setCategory(\Schoolbox\Resource\CategoryMapper::getInstance()->createModel()->setId($_POST['categoryID']))
        ->setHeading($_POST['textBoxHeading'])
        ->setBody($_POST['textBoxBody'])
        ->setDeletedAt($now->getDatabase());

    if (is_numeric($_POST['textBoxID'])) {
        $textbox->setId($_POST['textBoxID']);
    }

    $affected = \Schoolbox\Textbox\Mapper::getInstance()->save($textbox);
    $textbox->setId($affected[0]);

    // select this textBox
    $cim       = Component_InstanceMapper::getInstance();
    $instances = array_values($cim->findByInstanceId($_POST['componentID']));
    /* @var $instance Component_Instance */
    $instance = $instances[0];

    $ccm    = Component_ConfigMapper::getInstance();
    $config = new Component_Config();
    $config
        ->setInstanceId($instance->getId())
        ->setConfigDefinitionId($instance->getConfig('textbox')->getBy('key', 'textbox')->getId())
        ->setValue($textbox->getId());
    $ccm->save($config);

    SitePage::localRedir('/homepage/' . $_POST['originID']);
} else {
    if (is_numeric($_GET['delete'])) {
        try {
            $textbox = \Schoolbox\Textbox\Mapper::getInstance()->findById($_GET['delete']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            error(2);
            exit;
        }

        if ($groupHandler->getPermission($textbox->getCategory()->getId()) < WRITE_ACCESS) {
            error(8);
        }

        \Schoolbox\Textbox\Mapper::getInstance()->delete($textbox);

        SitePage::localRedir('/homepage/' . $_GET['originID']);
    }
}
