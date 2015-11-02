<?php
/** @var \Schoolbox\Application\WebRequest\Kernel $kernel */
$kernel = require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage();
$page->setDi($kernel->getDependencyInjector());
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
            // Also try to save width
            if (!empty($_POST['width'])) {
                $eportfolioHandler->updateGrid($_POST['width']);
            }
            $eportfolioHandler->saveDisplayProperties($_POST['colors']);
        } /* Ajax request for drag and drop of components
		 */
        elseif ($_POST['type'] == 'components') {
            $columns = array(
                'left' => $_POST['column1'],
                'right' => $_POST['column2'],
                'top' => $_POST['column3']
            );

            foreach ($columns as $position => $componentString) {
                // Explode the columns
                $components[$position] = explode(',', $componentString);

                $instanceCollection = new Schoolbox\Collection();

                // Update the db
                foreach ($components[$position] as $sequence => $componentID) {
                    if ($componentID) {
                        /** @var Component_Instance $inst */
                        $inst = Component_InstanceMapper::getInstance()->findById($componentID);
                        $inst->setSequence($sequence + 1); // because we prefer to not have 0s in the database
                        $inst->setPosition($position);
                        $instanceCollection->add($inst);
                    }
                }

                if ($instanceCollection->count()) {
                    Component_InstanceMapper::getInstance()->save($instanceCollection);
                }
            }
        } /* AJAX request to delete component from eportfolio
		 */
        elseif (is_numeric($_POST['delete'])) {
// locate the instance, locate its config instances, then blast them away
            /** @var Component_Instance $instance */
            $instance = $componentInstanceMapper->findById($_POST['delete']);
            $configs = $componentConfigInstanceMapper->findByColumn('component_instance_id', $instance->getId());

            foreach ($configs as $c) {
                $componentConfigInstanceMapper->delete($c);
            }

            $componentInstanceMapper->delete($instance);
        } /* POST new component to page and output render of component
		 */
        elseif (is_numeric($_POST['add']) || is_numeric($_POST['reload'])) {
            if (is_numeric($_POST['add'])) {
                $newComp = new Component_Instance();
                $newComp
                    ->setDefinitionId($_POST['add'])
                    ->setIdentifier($_POST['page_id'])
                    ->setModule('eportfolio')
                    ->setSequence(0)
                    ->setPosition('top');

                $ids = $componentInstanceMapper->save($newComp);

                $instanceID = $ids[0];
                // Save the component's configuration here
            } else {
                $instanceID = $_POST['reload'];
            }

            $componentInstanceMapper->cascadeSequence($eportfolioHandler->getSettingsPageOwner());

            // get the Instance's Definition's Controller
            $factory = new Component_Eportfolio_Factory(new \Schoolbox\User(), new SitePage(false));

            // $component instanceof Component_ControllerAbstract
            $componentList = $componentInstanceMapper->findByInstanceId($instanceID);
            $cList = array_values($componentList);

            $component = $factory->buildComponent($cList[0]);

            if (!($component instanceof Component_ControllerAbstract)) {
                die("<h2>Error adding component</h2><p>Could not find component to instantiate.</p>");
            }

            // If this is a new component, set its name
            if (is_numeric($_POST['add'])) {
                foreach ($component->getInstance()->getConfig() as $currentConfig) {
                    /** @var Component_ConfigDefinition $currentConfig */
                    if ($currentConfig->getKey() == 'componentname') {
                        $configInstance = Component_ConfigMapper::getInstance()->findByColumnList(array(
                            'component_config_definition_id' => $currentConfig->getId(),
                            'component_instance_id' => $_POST['from'],
                        ))->first();

                        if ($configInstance instanceof Component_Config) {
                            $componentName = $configInstance->getValue();

                            /* Presave a config for this newly added component
                             * so it always has the name we gave it
                             */
                            $newConfigInstance = new Component_Config();
                            $newConfigInstance->setId(null);
                            $newConfigInstance->setConfigDefinitionId($currentConfig->getId());
                            $newConfigInstance->setInstanceId($component->getInstance()->getId());
                            $newConfigInstance->setValue($componentName);

                            Component_ConfigMapper::getInstance()->save($newConfigInstance);

                            /* Force inherited name
                             */
                            $component->getInstance()->getConfig()->offsetGet($currentConfig->getId())
                                ->setDefaultValue($componentName);
                        } else {
                            $componentName = $currentConfig->getDefaultValue();
                        }
                    }
                }
            }

            $renderer = new Component_Renderer();
            $renderer->renderEportfolioComponent(
                new Eportfolio_Page_Instance(
                    $page,
                    $eportfolioHandler->getPageInstance()->getUid(),
                    $eportfolioHandler->getPageId(),
                    FULL_ACCESS
                ),
                $component
            );
        } /* AJAX new column widths
		 */
        elseif (is_numeric($_POST['leftColWidth']) && is_numeric($_POST['rightColWidth'])) {
            $eportfolioHandler->updateGrid(Scrub::text($_POST['leftColWidth']));
        } /* Update permissions and page title
		 */
        elseif (isset($_POST['update']) && ($_POST['update'] == 'page')) {
            \Eportfolio\PermissionMapper::getInstance()->updatePermissions($eportfolioHandler->getPageId(), $_POST['parent_permission'], $_POST['group_permission'], $_POST['individual_permission']);
            $eportfolioHandler->updatePageTitle(trim($_POST['pageTitle']));
            SitePage::localRedir("/eportfolio/{$eportfolioHandler->getPageInstance()->getUid()}/{$eportfolioHandler->getPageId()}");
        } /* Softdelete this page and redirect back to profile
		 */
        elseif (isset($_POST['deletePage'])) {
            $eportfolioHandler->softdelete();

            SitePage::localRedir("/eportfolio/{$eportfolioHandler->getPageInstance()->getUid()}/profile");
        } /* Update config options from homepage
		 */
        elseif (($_POST['type'] == 'config') && $_POST['component']) {
            $config = $_POST;

            /** @var Component_Instance $instance */
            $instance = reset($componentInstanceMapper->findByInstanceId($config['component']));
            $componentConfigDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();

            $configDefinitionCollection = $componentConfigDefinitionMapper->findByColumn(
                'component_definition_id', $instance->getDefinitionId()
            );

            $pageId = $config['page_id'];

            unset($config['page_id'], $config['type'], $config['component']);

            // much as I hate exceptional cases, there are exceptional cases...

            //remove the prepended '/homepage/'
            if (isset($config['src'])) {
                preg_match("#[^0-9]*([0-9]*)#i", $config['src'], $matches);
                if ($matches) {
                    $config['src'] = $matches[1];
                }
            }
            // prepare filter value
            if (isset($config['filter'])) {
                // retrieve the instance so we know how to insert this data
                $cclass = $instance->getDefinition()->getClass();
                /** @var \Component_ControllerAbstract $controller */
                $controller = new $cclass($instance, new \Schoolbox\User(), $page);
                $fields = $controller->getFilterFields();
                $val = 0;
                for ($i = 0; $i < count($fields); $i++) {
                    $add = isset($config['filter'][$fields[$i]]) && $config['filter'][$fields[$i]] ? 1 : 0;
                    $val += $add * pow(2, $i);
                }
                $config['filter'] = $val;
            }

            /** @var Component_ConfigDefinition $confD */
            foreach ($configDefinitionCollection as $confD) {
                if (isset($config[$confD->getKey()])) {
// this should be 1:1 relation, so return only the first instance of this definition
                    /** @var Component_Config $confI */
                    $confI = $componentConfigInstanceMapper->findByColumnList(array(
                        'component_config_definition_id' => $confD->getId(),
                        'component_instance_id'          => $instance->getId()
                    ))->first();
                    if (!($confI instanceof Component_Config)) {
                        $confI = new Component_Config();
                    }

                    if (!($confI->getId())) {
                        $confI->setConfigDefinitionId($confD->getId());
                        $confI->setInstanceId($instance->getId());
                    }
                    $confI->setValue($config[$confD->getKey()]);

                    $componentConfigInstanceMapper->save($confI);

                }
            }

            if (SitePage::detectAjax()) {
                // Just rebuild and refetch the component
                $factory       = new Component_Eportfolio_Factory(new \Schoolbox\User(), new SitePage(false));
                $componentList = $componentInstanceMapper->findByInstanceId($instance->getId());
                $cList         = array_values($componentList);
                $component     = $factory->buildComponent($cList[0]);
                $renderer      = new Component_Renderer();
                $renderer->renderEportfolioComponent(
                    new Eportfolio_Page_Instance(
                        $page,
                        $eportfolioHandler->getPageInstance()->getUid(),
                        $eportfolioHandler->getPageId(),
                        FULL_ACCESS
                    ),
                    $component
                );
                exit;
            } else {
                SitePage::localRedir("/eportfolio/{$eportfolioHandler->getPageInstance()->getUid()}/{$eportfolioHandler->getPageId()}");
            }
        }
    } else {
        error(19);
    }
} else {
/* Create page
	 */
    if (isset($_POST['page_title']) && strlen(trim($_POST['page_title'])) && isset($_POST['page_template']) && is_numeric($_POST['page_template'])) {
/* Create page for the currently logged-in user
		 */
        $userId = $_POST['userId'];
        $pageId = null;
        $pageTitle = $_POST['page_title'];
        $templateId = $_POST['page_template'];

        $authenticator = new Eportfolio_Authenticator($_SESSION['id'], $userId, null);

        if ($authenticator->canCreate()) {
            /* Create page and apply components (a page_id is generated)
			 */
            $eportfolioHandler = new Eportfolio($userId, $pageId);
            $eportfolioHandler->createEportfolioFromTemplate($pageTitle, $templateId);

            /* Apply permissions to the newly-created page
			 */
            Eportfolio\PermissionMapper::getInstance()->updatePermissions($eportfolioHandler->getPageId(), $_POST['parent_permission'], $_POST['group_permission'], $_POST['individual_permission']);

            /* Redirect to newly created page
			 */
            SitePage::localRedir("/eportfolio/{$eportfolioHandler->getUserId()}/{$eportfolioHandler->getPageId()}");
        } else {
            error(19);
        }
    } else {
        error(2);
    }
}
