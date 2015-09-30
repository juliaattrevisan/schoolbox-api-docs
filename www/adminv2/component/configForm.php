<?php
// The kernel contains the dependency injection engine.
// If you inject the dependency injection engine into Sitepage,
// it can use Twig to generate component content.
/** @var \Schoolbox\Application\WebRequest\Kernel $kernel */
$kernel = require_once dirname(__FILE__) . '/../../../include/Bootstrap.php';

$page = new SitePage();

if (!($_SESSION['user']->isSuperuser())) {
    error(19);
}

$page->setDi($kernel->getDependencyInjector());
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (!($_SESSION['user']->isSuperuser())) {
    error(19);
}

$componentInstanceMapper       = Component_InstanceMapper::getInstance();
$componentConfigInstanceMapper = Component_ConfigMapper::getInstance();

if ($_POST['type'] == 'components') {
    // AJAX request for drag and drop of components
    $columns = array(
        'left'  => $_POST['column1'],
        'right' => $_POST['column2'],
        'top'   => $_POST['column3']
    );

    foreach ($columns as $position => $componentString) {
        // Explode the columns
        $components[$position] = explode(',', $componentString);

        $instanceList = array();

        // Update the db
        foreach ($components[$position] as $sequence => $componentID) {
            if ($componentID) {
                try {
                    /** @var $inst Component_Instance */
                    $inst = Component_InstanceMapper::getInstance()->findById($componentID);
                    $inst->setSequence($sequence + 1); // because we prefer to not have 0s in the database///
                    $inst->setPosition($position);
                    $instanceList[] = $inst;
                } catch (Schoolbox\Mapper\Exception $e) {
                }
            }
        }

        Component_InstanceMapper::getInstance()->save($instanceList);
    }
} elseif (is_numeric($_POST['delete'])) {
    // AJAX request to delete component from homepage
    // locate the instance, locate its config instances, then blast them away
    try {
        /** @var Component_Instance $instance */
        $instance = $componentInstanceMapper->findById($_POST['delete']);
        $configs  = $componentConfigInstanceMapper->findByColumn('component_instance_id', $instance->getId());
        foreach ($configs as $c) {
            $componentConfigInstanceMapper->delete($c);
        }
        $componentInstanceMapper->delete($instance);
    } catch (Schoolbox\Mapper\Exception $e) {
    }
} elseif (is_numeric($_POST['cid']) && (is_numeric($_POST['add']) || is_numeric($_POST['reload']))) {
    // POST new component to page and output render of component
    if (is_numeric($_POST['add'])) {
        $newComp = new Component_Instance();
        $newComp
            ->setDefinitionId($_POST['add'])
            ->setIdentifier($_POST['cid'])
            ->setModule($_POST['context'])
            ->setSequence(0)
            ->setPosition('left');

        $ids        = $componentInstanceMapper->save($newComp);
        $instanceID = $ids[0];

        $componentInstanceMapper->cascadeSequence($_POST['cid']);
    } else {
        $instanceID = $_POST['reload'];
    }

    $factory = new Component_Homepage_Factory(new \Schoolbox\User(), $page);

    // $component instanceof Component_ControllerAbstract
    $componentList = $componentInstanceMapper->findByInstanceId($instanceID);
    $cList         = array_values($componentList);

    $component = $factory->buildComponent($cList[0]);

    if (!($component instanceof Component_ControllerAbstract)) {
        die("<h2>Error adding component</h2><p>Could not find component to instantiate.</p>");
    }
    $renderer = new Component_Renderer();
    $renderer->renderAdminComponent($component);
} elseif ($_GET['cid'] && $_GET['type'] == 'config' && $_GET['component']) {
//    // GET config options from homepage
//    $config = $_GET;
//
//    /** @var Component_Instance $instance */
//    $instance = reset($componentInstanceMapper->findByInstanceId($config['component']));
//
//    if (is_object($instance)) {
//        $componentConfigDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();
//
//        $configDefinitionCollection = $componentConfigDefinitionMapper->findByColumn(
//            'component_definition_id', $instance->getDefinitionId()
//        );
//
//        unset($config['cid'], $config['type'], $config['component']);
//
//        // Create a controller object for the config class to use when
//        // dehydrating config values into database
//        $cclass = $instance->getDefinition()->getClass();
//        /** @var Component_ControllerAbstract $controller */
//        $controller = new $cclass($instance, new \Schoolbox\User(), $page);
//
//        /** @var Component_ConfigDefinition $confD */
//        foreach ($configDefinitionCollection as $confD) {
//            $key = $confD->getKey();
//            if (isset($config[$key])) {
//                $configItem = Component_Config_Factory::build($key, $category, $config);
//                $value      = $configItem->dehydrate($config[$key], $controller);
//
//                // this should be 1:1 relation, so return only the first instance of this definition
//                /** @var Component_Config $confI */
//                $confI = $componentConfigInstanceMapper->findByColumnList(
//                    array(
//                        'component_config_definition_id' => $confD->getId(),
//                        'component_instance_id'          => $instance->getId()
//                    )
//                )->first();
//                if (!($confI instanceof Component_Config)) {
//                    $confI = new Component_Config();
//                }
//
//                if (!($confI->getId())) {
//                    $confI = new Component_Config();
//                    $confI->setConfigDefinitionId($confD->getId());
//                    $confI->setInstanceId($instance->getId());
//                }
//                $confI->setValue($value);
//
//                $componentConfigInstanceMapper->save($confI);
//
//            }
//        }
//        if (SitePage::detectAjax()) {
//            // Just refetch the instance
//            $instance   = reset($componentInstanceMapper->findByInstanceId($instance->getId()));
//            $controller = new $cclass($instance, new \Schoolbox\User(), $page);
//
//            $folderMapper = new \Schoolbox\Resource\CategoryMapper();
//            $folder = $folderMapper->findById($_GET['cid']);
//            $renderer = new Component_Renderer();
//            $renderer->renderHomepageComponent($folder, $controller);
//            exit;
//        } else {
//            SitePage::localRedir("/homepage/" . $_GET['cid']);
//        }
//    }
//    SitePage::localRedir("/homepage/" . $_GET['cid']);
}
