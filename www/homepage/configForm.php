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
                    $inst->sequence = $sequence + 1; // because we prefer to not have 0s in the database///
                    $inst->position = $position;

                    $instanceList[] = $inst;
                }
			}

            Component_InstanceMapper::getInstance()->save($instanceList);
		}
	}


	// AJAX request to delete component from homepage
	else if ($_POST['cid'] && is_numeric($_POST['delete'])) {

        // locate the instance, locate its config instances, then blast them away
        $instance = $componentInstanceMapper->findById($_POST['delete']);
        $configs = $componentConfigInstanceMapper->findByColumn('component_instance_id', $instance->id);

        foreach ($configs as $c) {
            $componentConfigInstanceMapper->delete($c);
        }
        $componentInstanceMapper->delete($instance);

	}


	// POST new component to page and output render of component
	else if ($_POST['cid'] && is_numeric($_POST['add'])) {

        $newComp = new Component_Instance();
        $newComp->definitionId = $_POST['add'];
        $newComp->identifier = $_POST['cid'];
        $newComp->module = 'homepage';
        $newComp->sequence = 0;
        $newComp->position = 'left';

        $componentInstanceMapper->save($newComp);


        $instanceID = Database::getInstance()->lastInsertId();

        // check perms
        $groupHandler = new Group(HOMEPAGE_SERVICE);
        if (($permission = $groupHandler->getPermission($_POST['cid'])) < WRITE_ACCESS) {
            exit;
        }

        // get the Instance's Definition's Controller
        $factory = new Component_Homepage_Factory(new User(), new SitePage(false));

        // $component instanceof Component_ControllerAbstract
        $component = $factory->buildComponent($instanceID);

        if(!$component) {
            die("<h2>Error adding component</h2><p>Could not find component to instantiate.</p>");
        }

        $templateTarget = "component_position_{$component->instance->position}";
        $templateHandle = "component_{$component->instance->id}";
        $templateHandle = TEMPLATE_content;

        $component->setup(array('id' => $_POST['cid']), $component->instance->getConfigArray());
        $component->parseConfig();

        if ($component->execute() !== false) {
            if ($component->renderTemplate($templateHandle) !== false) {
                $component->parse($templateTarget, $templateHandle, true);
            }
        }

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

        $instance = $componentInstanceMapper->findById($config['component']);
        $componentConfigDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();

        $configDefinitionCollection = $componentConfigDefinitionMapper->findByColumn('component_definition_id', $instance->definitionId);

		unset($config['cid'], $config['type'], $config['component']);

        // much as I hate exceptional cases, this is an exceptional case: remove the prepended '/homepage/'
        if(isset($config['src'])) {
            preg_match("#[^0-9]*([0-9]*)#i", $config['src'], $matches);
            if($matches) {
                $config['src'] = $matches[1];
            }
        }

        foreach($configDefinitionCollection as $confD) {
            if(isset($config[$confD->key])) {

                // this should be 1:1 relation, so return only the first instance of this definition
                $confI = $componentConfigInstanceMapper->findByColumnList(array(
                    'component_config_definition_id' => $confD->id,
                    'component_instance_id'          => $instance->id
                ))->current();

                // printf("Changing value of key `%s` from '%s' to '%s'<br/>\n", $confD->key, $confI->value, $config[$confD->key]);

                if(!($confI->id)) {
                    $confI = new Component_Config();
                    $confI->configDefinitionId = $confD->id;
                    $confI->instanceId = $instance->id;
                }
                $confI->value = $config[$confD->key];

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
