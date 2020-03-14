<?php

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

// The kernel contains the dependency injection engine.
// If you inject the dependency injection engine into Sitepage,
// it can use Twig to generate component content.
require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

$page = new SitePage();
$page->setDi($kernel->getDependencyInjector());
$groupHandler = new Group(UserAccess::SERVICE_HOMEPAGE);

if (is_numeric($_REQUEST['cid'])) {
    if ($groupHandler->getPermission($_REQUEST['cid']) < UserAccess::ACCESS_WRITE) {
        error(19);
    }

    $componentInstanceMapper       = Component_InstanceMapper::getInstance();
    $componentConfigInstanceMapper = Component_ConfigMapper::getInstance();

    /* The RETURN_URL twig global presumes that the encoded portion comes from $_GET['page']
     * without it, returnUrl will be an empty string which, when clicked, sends users to a
     * completely blank screen. This hack fulfils the expected requirements in
     * library/Alaress/Application/Kernel.php:876
     */

    //This is a hack to trick twig into thinking the component is on a homepage
    $_GET['page'] = '/homepage/' . $_REQUEST['cid'];


    // AJAX request for drag and drop of components
    if ($_POST['cid'] && $_POST['type'] == 'components') {
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
    } // AJAX request to delete component from homepage
    elseif ($_POST['cid'] && is_numeric($_POST['delete'])) {
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
    } // POST new component to page and output render of component
    elseif (is_numeric($_POST['cid']) && (is_numeric($_POST['add']) || is_numeric($_POST['reload']))) {
        if (is_numeric($_POST['add'])) {
            $newComp = new Component_Instance();
            $newComp
                ->setDefinitionId($_POST['add'])
                ->setIdentifier($_POST['cid'])
                ->setModule('homepage')
                ->setSequence(0)
                ->setPosition('top');

            // check perms
            if (($permission = $groupHandler->getPermission($_POST['cid'])) < UserAccess::ACCESS_WRITE) {
                exit;
            }

            $ids        = $componentInstanceMapper->save($newComp);
            $instanceID = $newComp->getId();

            $componentInstanceMapper->cascadeSequence($_POST['cid']);
        } else {
            $instanceID = $_POST['reload'];
        }

        $factory = new Component_Homepage_Factory($page->getDi()->get('session')->get('user'), $page);

        $instance = $componentInstanceMapper->findByInstanceId($instanceID);
        /** @var Component_Homepage_ControllerAbstract $component */
        $component = $factory->buildComponent($instance);

        if (!($component instanceof Component_ControllerAbstract)) {
            die("<h2>Error adding component</h2><p>Could not find component to instantiate.</p>");
        }

        $page->localise();

        $folderMapper = new \Schoolbox\Resource\Folder\Mapper();
        $folder = $folderMapper->findById($_POST['cid']);
        $renderer = new Component_Renderer();
        $renderer->renderHomepageComponent($folder, $component);
    } // AJAX Save homepage colours
    elseif ($_POST['cid'] && is_array($_POST['colors'])) {
        $homepage = new Homepage($_POST['cid']);
        // Also try to save width
        if (!empty($_POST['width'])) {
            // Mangle width into percentages
            $homepage->updateGrid($_POST['width']);
        }
        $homepage->saveDisplayProperties($_POST['colors']);
    } // GET config options from homepage
    elseif ($_GET['cid'] && $_GET['type'] == 'config' && $_GET['component']) {
        $config = $_GET;

        /** @var Component_Instance $instance */
        $instance = $componentInstanceMapper->findByInstanceId($config['component']);

        if (is_object($instance)) {
            $componentConfigDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();
            $folderMapper = FolderMapper::getInstance();
            $folder = $folderMapper->findById($config['cid']);

            $configDefinitionCollection = $componentConfigDefinitionMapper->findByColumn(
                'component_definition_id', $instance->getDefinitionId()
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
                $renderer->renderHomepageComponent($folder, $controller);
                exit;
            } else {
                SitePage::localRedir("/homepage/" . $_GET['cid']);
            }
        }
        SitePage::localRedir("/homepage/" . $_GET['cid']);
    } // AJAX new column widths
//    elseif ($_POST['cid'] && is_numeric($_POST['leftColWidth']) && is_numeric($_POST['rightColWidth'])) {
//        $homepage = new Homepage($_POST['cid']);
//        $homepage->updateColumnWidths(Scrub::text($_POST['leftColWidth']), Scrub::text($_POST['rightColWidth']));
//    }
} // no cid provided
else {
    error(2);
}
