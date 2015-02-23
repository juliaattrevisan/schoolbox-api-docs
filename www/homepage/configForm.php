<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page         = new SitePage();
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (is_numeric($_REQUEST['cid'])) {
    if ($groupHandler->getPermission($_REQUEST['cid']) < WRITE_ACCESS) {
        error(19);
    }

    $componentInstanceMapper       = Component_InstanceMapper::getInstance();
    $componentConfigInstanceMapper = Component_ConfigMapper::getInstance();


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
                ->setPosition('left');

            // check perms
            if (($permission = $groupHandler->getPermission($_POST['cid'])) < WRITE_ACCESS) {
                exit;
            }

            $ids        = $componentInstanceMapper->save($newComp);
            $instanceID = $ids[0];

            $componentInstanceMapper->cascadeSequence($_POST['cid']);
        } else {
            $instanceID = $_POST['reload'];
        }

        // get the Instance's Definition's Controller
        $factory = new Component_Homepage_Factory(new \Schoolbox\User(), new SitePage(false));

        // $component instanceof Component_ControllerAbstract
        $componentList = $componentInstanceMapper->findByInstanceId($instanceID);
        $cList         = array_values($componentList);

        $component = $factory->buildComponent($cList[0]);

        if (!($component instanceof Component_ControllerAbstract)) {
            die("<h2>Error adding component</h2><p>Could not find component to instantiate.</p>");
        }

        $folderMapper = new \Schoolbox\Resource\CategoryMapper();
        $folder = $folderMapper->findById($_POST['cid']);

        $templateTarget = "component_position_{$component->getInstance()->getPosition()}";
        $templateHandle = "component_{$component->getInstance()->getId()}";
        $templateHandle = TEMPLATE_content;

        $component->setup($folder, $component->getInstance()->getConfigArray());

        if ($component->execute() !== false) {
            $component->parseConfig();
            if ($component->renderTemplate($templateHandle) !== false) {
                $component->parse($templateTarget, $templateHandle, true);
            }
        }

        /* Accumulate folder codes
         */
        $temp = array();

        foreach ($folder->getCodes() as $currentCode) {
            $temp[] = $currentCode->getCode();
        }

        $folderCodes = implode(', ', $temp);

        $component->getPageTemplate()->setVar('CODE', urlencode(urlencode($folderCodes)));
        $component->getPageTemplate()->setVar('HEADING', Scrub::text($folder->getName()));
        $component->getPageTemplate()->setVar('SITE_LOCATION', Cfg::get('site_location'));
        $component->getPageTemplate()->pparse($templateTarget, $templateHandle);

    } // AJAX Save homepage colours
    elseif ($_POST['cid'] && is_array($_POST['colors'])) {
        $homepage = new Homepage($_POST['cid']);
        $color    = array();
        foreach ($_POST['colors'] as $key => $value) {
            $color[$key] = substr($value, 1);
        }
        $homepage->saveDisplayProperties($color);
    } // GET config options from homepage
    elseif ($_GET['cid'] && $_GET['type'] == 'config' && $_GET['component']) {
        $config = $_GET;

        /** @var Component_Instance $instance */
        $instance = reset($componentInstanceMapper->findByInstanceId($config['component']));

        if (is_object($instance)) {
            $componentConfigDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();

            $configDefinitionCollection = $componentConfigDefinitionMapper->findByColumn(
                'component_definition_id', $instance->getDefinitionId()
            );

            unset($config['cid'], $config['type'], $config['component']);

            // Create a controller object for the config class to use when
            // dehydrating config values into database
            $cclass = $instance->getDefinition()->getClass();
            /** @var Component_ControllerAbstract $controller */
            $controller = new $cclass($instance, new \Schoolbox\User(), $page);
            // No need to execute at all just to set configuration,
            // and doing so can break components that rely on context
//            $controller->execute();

            /** @var Component_ConfigDefinition $confD */
            foreach ($configDefinitionCollection as $confD) {
                $key = $confD->getKey();
                if (isset($config[$key])) {
                    $configItem = Component_Config_Factory::build($key, $category, $config);
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

                    $componentConfigInstanceMapper->save($confI);

                }
            }
        }
        SitePage::localRedir("/homepage/" . $_GET['cid']);
    } // AJAX new column widths
    elseif ($_POST['cid'] && is_numeric($_POST['leftColWidth']) && is_numeric($_POST['rightColWidth'])) {
        $homepage = new Homepage($_POST['cid']);
        $homepage->updateColumnWidths(Scrub::text($_POST['leftColWidth']), Scrub::text($_POST['rightColWidth']));
    }
} // no cid provided
else {
    error(2);
}
