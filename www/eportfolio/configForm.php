<?php
require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage();
$groupHandler = new Group(HOMEPAGE_SERVICE);

/* Update page, or its stuff
 */
if (isset($_POST['page_id']) && is_numeric($_POST['page_id'])) {

	/* Check permissions
	 */
	$eportfolioHandler = new Eportfolio($_SESSION['id'], $_POST['page_id']);

	$authenticator = new Eportfolio_Authenticator($_SESSION['id'], $eportfolioHandler->getSettingsPageOwner(), $_POST['page_id']);

	/* If $user can modify page, continue; otherwise die
	 * Superusers, staff and page owners can modify contents/settings.
	 */
	if ($authenticator->canModify()) {
		$componentInstanceMapper = Component_InstanceMapper::getInstance();
		$componentConfigInstanceMapper = Component_ConfigMapper::getInstance();

		/* Ajax save page colours. When instantiating the Eportfolio object, assume page owner
		 * is currently logged in. The colourpicker is built to only send one primary name/key'
		 * pair, rather than two, which is what would be nice.
		 */
		if (is_array($_POST['colors'])) {
			$color = array();

			foreach ($_POST['colors'] as $key => $value) {
				$color[$key] = substr($value, 1);
			}

			$eportfolioHandler->saveDisplayProperties($color);
		}
		/* Save page title
		 */
		else if (isset($_POST['user_id']) && is_numeric($_POST['user_id']) && strlen(trim($_POST['pageTitle']))) {
			$eportfolioHandler->updatePageTitle(trim($_POST['pageTitle']));

			SitePage::localRedir("/eportfolio/{$eportfolioHandler->getUserId()}/{$eportfolioHandler->getPageId()}");
		}
		/* Ajax request for drag and drop of components
		 */
		else if ($_POST['type'] == 'components') {
			$columns = array(
				'left' => $_POST['column1'],
				'right' => $_POST['column2'],
				'top' => $_POST['column3']
			);

			foreach ($columns as $position => $componentString) {
				// Explode the columns
				$components[$position] = explode(',', $componentString);

				$instanceCollection = new Collection();

				// Update the db
				foreach ($components[$position] as $sequence => $componentID) {
					if ($componentID) {
						$inst = Component_InstanceMapper::getInstance()->findById($componentID);
						$inst->sequence = $sequence + 1; // because we prefer to not have 0s in the database
						$inst->position = $position;

						$instanceCollection[] = $inst;
					}
				}

				if ($instanceCollection->count()) {
					Component_InstanceMapper::getInstance()->save($instanceCollection);
				}
			}
		}
		/* AJAX request to delete component from eportfolio
		 */
		else if (is_numeric($_POST['delete'])) {

			// locate the instance, locate its config instances, then blast them away
			$instance = $componentInstanceMapper->findById($_POST['delete']);
			$configs = $componentConfigInstanceMapper->findByColumn('component_instance_id', $instance->id);

			foreach ($configs as $c) {
				$componentConfigInstanceMapper->delete($c);
			}

			$componentInstanceMapper->delete($instance);
		}
		/* POST new component to page and output render of component
		 */
		else if (is_numeric($_POST['add'])) {
			$newComp = new Component_Instance();
			$newComp->definitionId = $_POST['add'];
			$newComp->identifier = $_POST['page_id'];
			$newComp->module = 'eportfolio';
			$newComp->sequence = 0;
			$newComp->position = 'left';

			$ids = $componentInstanceMapper->save($newComp);

			$instanceID = $ids[0];

			$componentInstanceMapper->cascadeSequence($eportfolioHandler->getSettingsPageOwner());

			// get the Instance's Definition's Controller
			$factory = new Component_Eportfolio_Factory(new User(), new SitePage(false));

			// $component instanceof Component_ControllerAbstract
			$componentList = $componentInstanceMapper->findByInstanceId($instanceID);
			$cList = array_values($componentList);

			$component = $factory->buildComponent($cList[0]);

			if(!($component instanceof Component_ControllerAbstract)) {
				die("<h2>Error adding component</h2><p>Could not find component to instantiate.</p>");
			}

			$templateTarget = "component_position_{$component->instance->position}";
			$templateHandle = "component_{$component->instance->id}";
			$templateHandle = TEMPLATE_content;

			/* -_-
			 */
			$userHandler = new User();
			$user = $userHandler->getById($eportfolioHandler->getSettingsPageOwner());

			$component->setup($user, $component->instance->getConfigArray() );

			if ($component->execute() !== false) {
				$component->parseConfig();

				if ($component->renderTemplate($templateHandle) !== false) {
					$component->parse($templateTarget, $templateHandle, true);
				}
			}

			/* These aren't normally parsed so have to do it explicitly
			 */
			$component->getPageTemplate()->setVar(array(
				'USER_ID' => $eportfolioHandler->getUserId(),
				'PAGE_ID' => $eportfolioHandler->getPageId(),
			));

			$component->getPageTemplate()->pparse($templateTarget, $templateHandle);
		}
		/* AJAX new column widths
		 */
		else if (is_numeric($_POST['leftColWidth']) && is_numeric($_POST['rightColWidth'])) {
			$eportfolioHandler->updateColumnWidths(Scrub::text($_POST['leftColWidth']), Scrub::text($_POST['rightColWidth']));
		}
		/* Update permissions
		 */
		else if (isset($_POST['update']) && ($_POST['update'] == 'permissions')) {
			\Eportfolio\PermissionMapper::getInstance()->updatePermissions($eportfolioHandler->getPageId(), $_POST['parent_permission'], $_POST['group_permission'], $_POST['individual_permission']);

			SitePage::localRedir("/eportfolio/{$eportfolioHandler->getUserId()}/{$eportfolioHandler->getPageId()}");
		}
		/* Softdelete this page and redirect back to profile
		 */
		else if (isset($_POST['deletePage'])) {
			$eportfolioHandler->softdelete();

			SitePage::localRedir("/eportfolio/{$eportfolioHandler->getUserId()}/profile");
		}
		/* Update config options from homepage
		 */
		else if (($_POST['type'] == 'config') && $_POST['component']) {
			$config = $_POST;

			$instance = reset($componentInstanceMapper->findByInstanceId($config['component']));
			$componentConfigDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();

			$configDefinitionCollection = $componentConfigDefinitionMapper->findByColumn('component_definition_id', $instance->definitionId);

			$pageId = $config['page_id'];

			unset($config['page_id'], $config['type'], $config['component']);

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
				$cclass = $instance->definition->class;
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

			foreach($configDefinitionCollection as $confD) {
				if (isset($config[$confD->key])) {

					// this should be 1:1 relation, so return only the first instance of this definition
					$confI = $componentConfigInstanceMapper->findByColumnList(array(
						'component_config_definition_id' => $confD->id,
						'component_instance_id'          => $instance->id
					))->current();

					printf("Changing value of key `%s` from '%s' to '%s'<br/>\n", $confD->key, $confI->value, $config[$confD->key]);

					if(!($confI->id)) {
						$confI = new Component_Config();
						$confI->configDefinitionId = $confD->id;
						$confI->instanceId = $instance->id;
					}
					$confI->value = $config[$confD->key];

					$componentConfigInstanceMapper->save($confI);

				}
			}

			SitePage::localRedir("/eportfolio/{$eportfolioHandler->getUserId()}/{$eportfolioHandler->getPageId()}");
		}
	}
	else {
		error(19);
	}
}
else {

	/* Create page
	 */
	if (isset($_POST['page_title']) && strlen(trim($_POST['page_title'])) && isset($_POST['page_template']) && is_numeric($_POST['page_template'])) {

		/* Create page for the currently logged-in user
		 */
		$userId = $_SESSION['id'];
		$pageId = null;
		$pageTitle = Scrub::text($_POST['page_title']);
		$templateId = Scrub::text($_POST['page_template']);

		/* Create page and apply components (a page_id is generated)
		 */
		$eportfolioHandler = new Eportfolio($userId, $pageId);
		$eportfolioHandler->createEportfolioFromTemplate($pageTitle, $templateId);

		/* Apply permissions to the newly-created page
		 */
		Eportfolio_PermissionMapper::getInstance()->updatePermissions($eportfolioHandler->getPageId(), $_POST['parent_permission'], $_POST['group_permission'], $_POST['individual_permission']);

		/* Redirect to newly created page
		 */
		SitePage::localRedir("/eportfolio/{$eportfolioHandler->getUserId()}/{$eportfolioHandler->getPageId()}");
	}
	else {
		error(2);
	}
}
