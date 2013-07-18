<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage();
$groupHandler = new Group(HOMEPAGE_SERVICE);

if (is_numeric($_REQUEST['cid'])) {
	if ($groupHandler->getPermission($_REQUEST['cid']) < WRITE_ACCESS) {
		error(19);
	}

	$componentInstanceMapper = Component_InstanceMapper::getInstance();
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
                if($componentID) {
                    $inst = Component_InstanceMapper::getInstance()->findById($componentID);
                    /** @var $inst Component_Instance */
	                if($inst !== false) {
	                    $inst->setSequence($sequence + 1); // because we prefer to not have 0s in the database///
	                    $inst->setPosition($position);

	                    $instanceList[] = $inst;
	                }
                }
			}

            Component_InstanceMapper::getInstance()->save($instanceList);
		}
	}


	// AJAX request to delete component from homepage
	else if ($_POST['cid'] && is_numeric($_POST['delete'])) {

        // locate the instance, locate its config instances, then blast them away
        /** @var Component_Instance $instance */
        $instance = $componentInstanceMapper->findById($_POST['delete']);

		if($instance !== false ) {
	        $configs = $componentConfigInstanceMapper->findByColumn('component_instance_id', $instance->getId());

	        foreach ($configs as $c) {
	            $componentConfigInstanceMapper->delete($c);
	        }
	        $componentInstanceMapper->delete($instance);
		}

	}


	// POST new component to page and output render of component
	else if (is_numeric($_POST['cid']) && is_numeric($_POST['add'])) {

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

        $ids = $componentInstanceMapper->save($newComp);
        $instanceID = $ids[0];

        $componentInstanceMapper->cascadeSequence($_POST['cid']);

        // get the Instance's Definition's Controller
        $factory = new Component_Homepage_Factory(new User(), new SitePage(false));

        // $component instanceof Component_ControllerAbstract
        $componentList = $componentInstanceMapper->findByInstanceId($instanceID);
        $cList = array_values($componentList);

        $component = $factory->buildComponent($cList[0]);

        if(!($component instanceof Component_ControllerAbstract)) {
            die("<h2>Error adding component</h2><p>Could not find component to instantiate.</p>");
        }

        $catHandler = new Category;
        $category = $catHandler->getByID($_POST['cid']);

        $templateTarget = "component_position_{$component->getInstance()->getPosition()}";
        $templateHandle = "component_{$component->getInstance()->getId()}";
        $templateHandle = TEMPLATE_content;

        $component->setup($category, $component->getInstance()->getConfigArray() );

        if ($component->execute() !== false) {
            $component->parseConfig();
            if ($component->renderTemplate($templateHandle) !== false) {
                $component->parse($templateTarget, $templateHandle, true);
            }
        }

        //
        // Forgive me for I have sinned and copy-pasted this (double encoding
        // and all) from www/homepage/index.php
        $categoryCodes = $catHandler->getCodeForCategory($_POST['cid']);
        $component->getPageTemplate()->setVar('CODE', urlencode(urlencode($categoryCodes['codes'])));
        $component->getPageTemplate()->setVar('SITE_LOCATION', Cfg::get('site_location'));

        $component->getPageTemplate()->pparse($templateTarget, $templateHandle);

	}


	// AJAX Save homepage colours
	else if ($_POST['cid'] && is_array($_POST['colors'])) {
        $homepage = new Homepage($_POST['cid']);
        $color = array();
        foreach ($_POST['colors'] as $key => $value) {
			$color[$key] = substr($value, 1);
		}
		$homepage->saveDisplayProperties($color);
	}


	// GET config options from homepage
	else if ($_GET['cid'] && $_GET['type'] == 'config' && $_GET['component']) {
        $config = $_GET;

        /** @var Component_Instance $instance */
        $instance = reset($componentInstanceMapper->findByInstanceId($config['component']));
        $componentConfigDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();

        $configDefinitionCollection = $componentConfigDefinitionMapper->findByColumn(
            'component_definition_id', $instance->getDefinitionId()
        );

        unset($config['cid'], $config['type'], $config['component']);

        // much as I hate exceptional cases, there are exceptional cases...

        //remove the prepended '/homepage/'
        if(isset($config['src'])) {
            preg_match("#[^0-9]*([0-9]*)#i", $config['src'], $matches);
            if($matches) {
                $config['src'] = $matches[1];
            }
        }
        // prepare filter value
        if(isset($config['filter'])) {
            // retrieve the instance so we know how to insert this data
            $cclass = $instance->getDefinition()->getClass();
            $controller = new $cclass($instance, new User(), $page);
            $controller->execute();
            $fields = $controller->getFilterFields();
            $val = 0;
            for($i = 0; $i < count($fields); $i++) {
                $add = isset($config['filter'][$fields[$i]]) && $config['filter'][$fields[$i]] ? 1 : 0;
                $val += $add * pow(2, $i);
            }
            $config['filter'] = $val;
        }

        /** @var Component_ConfigDefinition $confD */
        foreach($configDefinitionCollection as $confD) {
            if(isset($config[$confD->getKey()])) {

                // this should be 1:1 relation, so return only the first instance of this definition
                /** @var Component_Config $confI */
                $confI = $componentConfigInstanceMapper->findByColumnList(array(
                    'component_config_definition_id' => $confD->getId(),
                    'component_instance_id'          => $instance->getId()
                ))->first();
                if (!($confI instanceof Component_Config)) {
                    $confI = new Component_Config();
                }

                printf("Changing value of key `%s` from '%s' to '%s'<br/>\n", $confD->getKey(), $confI->getValue(), $config[$confD->getKey()]);

                if(!($confI->getId())) {
                    $confI = new Component_Config();
                    $confI->setConfigDefinitionId($confD->getId());
                    $confI->setInstanceId($instance->getId());
                }
                $confI->setValue($config[$confD->getKey()]);

                $componentConfigInstanceMapper->save($confI);

            }
        }
        SitePage::localRedir("/homepage/" . $_GET['cid']);
    }


    // AJAX new column widths
	else if ($_POST['cid'] && is_numeric($_POST['leftColWidth']) && is_numeric($_POST['rightColWidth'])) {
        $homepage = new Homepage($_POST['cid']);
		$homepage->updateColumnWidths(Scrub::text($_POST['leftColWidth']), Scrub::text($_POST['rightColWidth']));
	}
}

// no cid provided
else {
	error(2);
}
