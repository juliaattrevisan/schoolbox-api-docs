<?php

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Error\Mapper as ErrorMapper;

// The kernel contains the dependency injection engine.
// If you inject the dependency injection engine into Sitepage,
// it can use Twig to generate component content.
require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$groupHandler = new Group(UserAccess::SERVICE_HOMEPAGE);
$folderMapper = FolderMapper::getInstance();

if (is_numeric($_REQUEST['cid'])) {
    if ($groupHandler->getPermission($_REQUEST['cid']) < UserAccess::ACCESS_WRITE) {
        error(19);
    }

    $componentInstanceMapper       = Component_InstanceMapper::getInstance();
    $componentConfigInstanceMapper = Component_ConfigMapper::getInstance();

    $cid = is_numeric($_POST['cid'] ?? false) ? (int)$_POST['cid'] : null;

    // AJAX request for drag and drop of components
    if ($cid && ($_POST['type'] ?? '') == 'components') {
        $columns = array(
            'left'  => $_POST['column1'],
            'right' => $_POST['column2'],
            'top'   => $_POST['column3'],
        );

        foreach ($columns as $position => $componentString) {
            // Explode the columns
            $components[$position] = explode(',', $componentString);

            $instanceList = array();

            // Update the db
            foreach ($components[$position] as $sequence => $componentID) {
                if ($componentID) {
                    try {
                        /** @var Component_Instance $inst */
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

        // Check if the component has been deleted, so the client can display a warning
        // otherwise, the client will just re-render the component
        if ($_POST['movedComponentId']) {
            $instance = $componentInstanceMapper->findByInstanceId($_POST['movedComponentId']);
            if (is_object($instance)) {
                $page->outputJson(['hasComponentBeenDeleted' => false], false, 200);
            } else {
                $page->outputJson(['hasComponentBeenDeleted' => true], false, 200);
            }
        }
    } elseif ($cid && is_numeric($_POST['delete'] ?? false)) { // AJAX request to delete component from homepage
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
    } elseif (is_numeric($cid) && (is_numeric($_POST['add'] ?? false) || is_numeric($_POST['reload'] ?? null))) { // POST new component to page and output render of component
        if (is_numeric($_POST['add'] ?? false)) {
            $newComp = new Component_Instance();
            $newComp
                ->setDefinitionId($_POST['add'])
                ->setIdentifier($cid)
                ->setModule('homepage')
                ->setSequence(0)
                ->setPosition('top');

            // check perms
            if (($permission = $groupHandler->getPermission($cid)) < UserAccess::ACCESS_WRITE) {
                exit;
            }

            $ids        = $componentInstanceMapper->save($newComp);
            $instanceID = $newComp->getId();

            $componentInstanceMapper->cascadeSequence($cid);
        } else {
            $instanceID = $_POST['reload'];
        }

        $factory = new Component_Homepage_Factory($page->getDi()->get('session')->get('user'), $page);

        $instance = $componentInstanceMapper->findByInstanceId($instanceID);
        /** @var Component_Homepage_ControllerAbstract $component */
        $component = $factory->buildComponent($instance);

        // If the request expects JSON, we don't need to render the component
        if (SitePage::detectJson()) {
            if (!($component instanceof Component_ControllerAbstract)) {
                $page->outputJson("error: couldn't create component", false, 409);
                exit;
            }

            $page->outputJson(array('instanceID' => $instanceID), false, 200);
        } else {
            if (!($component instanceof Component_ControllerAbstract)) {
                exit("<div class='alert alert-box'>Couldn't create component.</div>");
            }
            $folder   = $folderMapper->findById($cid);
            $renderer = new Component_Renderer();
            $renderer->renderHomepageComponent($page, $folder, $component);
        }
    } elseif ($cid && is_array($_POST['colors'] ?? null)) { // AJAX Save homepage colours
        $homepage = new Homepage($cid);
        // Also try to save width
        if (!empty($_POST['width'])) {
            // Mangle width into percentages
            $homepage->updateGrid($_POST['width']);
        }
        $homepage->saveDisplayProperties($_POST['colors']);
    } elseif ($cid && $_POST['type'] == 'config' && $_POST['component']) {
        // GET config options from homepage
        $config = $_POST;

        /** @var Component_Instance $instance */
        $instance = $componentInstanceMapper->findByInstanceId($config['component'], $config['cid']);

        if (is_object($instance)) {
            $componentConfigDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();
            try {
                $folder                          = $folderMapper->findById($config['cid']);
            } catch (Schoolbox\Mapper\Exception $e) {
                $page->outputJson('folder was deleted or does not exist', false, 400);
                return;
            }

            $configDefinitionCollection = $componentConfigDefinitionMapper->findByColumn(
                'component_definition_id',
                $instance->getDefinitionId()
            );

            unset($config['cid'], $config['type'], $config['component']);

            // Create a controller object for the config class to use when
            // dehydrating config values into database
            $cclass = $instance->getDefinition()->getClass();
            /** @var Component_Homepage_ControllerAbstract $controller */
            $controller = new $cclass($instance, $page->getDi()->get('session')->get('user'), $page);
            // No need to execute at all just to set configuration,
            // and doing so can break components that rely on context
            //            $controller->execute();

            /** @var Component_Config[] $configInstances */
            $configInstances = $componentConfigInstanceMapper->createCollection();
            /** @var Component_ConfigDefinition $confD */
            foreach ($configDefinitionCollection as $confD) {
                $key = $confD->getKey();
                if (isset($config[$key])) {
                    $configItem = Component_Config_Factory::build($key, null, $config);
                    $value      = $configItem->dehydrate($config[$key], $controller);
                    // if src is the current folder id, reset the value to empty string
                    if ($key === 'src' && (int)$value === $folder->getId()) {
                        $value = '';
                    }

                    // this should be 1:1 relation, so return only the first instance of this definition
                    /** @var Component_Config $confI */
                    $confI = $componentConfigInstanceMapper->findByColumnList(
                        array(
                            'component_config_definition_id' => $confD->getId(),
                            'component_instance_id'          => $instance->getId()
                        )
                    )->first();
                    if (!($confI instanceof Component_Config)) {
                        $confI = new Component_Config();
                    }

                    if (!($confI->getId())) {
                        $confI = new Component_Config();
                        $confI->setConfigDefinitionId($confD->getId());
                        $confI->setInstanceId($instance->getId());
                    }
                    $confI->setValue($value);

                    $configInstances[] = $confI;
                }
            }

            if (count($configInstances)) {
                $componentConfigInstanceMapper->getDatabase()->transact(function () use ($componentConfigInstanceMapper, $configInstances) {
                    $componentConfigInstanceMapper->save($configInstances);
                });
            }

            if (SitePage::detectAjax()) {
                // Just refetch the instance
                $instance   = $componentInstanceMapper->findByInstanceId($instance->getId());

                $controller = new $cclass($instance, $page->getDi()->get('session')->get('user'), $page);
                $renderer   = new Component_Renderer();
                $renderer->renderHomepageComponent($page, $folder, $controller);
                exit;
            } else {
                SitePage::localRedir("/homepage/" . $_POST['cid']);
            }
        }
        $page->outputJson('error: component does not exist', false, 409);
    }
} else { // no cid provided
    error(2);
}
