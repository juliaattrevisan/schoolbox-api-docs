<?php

use Eportfolio\PermissionMapper as EportfolioPermissionMapper;
use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();
$groupHandler = new Group(UserAccess::SERVICE_HOMEPAGE);

// Update page, or its stuff

if (isset($_POST['page_id']) && is_numeric($_POST['page_id'])) {
    // Check permissions

    $eportfolioHandler = new Eportfolio($_SESSION['id'], $_POST['page_id']);
    $authenticator = new Eportfolio_Authenticator($_SESSION['user'], $eportfolioHandler->getPageInstance()->getOwner(), $eportfolioHandler->getPageInstance()->getPageId());

    if ($authenticator->canModify()) {
        $componentInstanceMapper       = Component_InstanceMapper::getInstance();
        $componentConfigInstanceMapper = Component_ConfigMapper::getInstance();

        if (is_array($_POST['colors'] ?? false)) {
            // Update page colours and widths

            $color = [];

            if (!empty($_POST['width'])) {
                $eportfolioHandler->updateGrid($_POST['width']);
            }

            $eportfolioHandler->saveDisplayProperties($_POST['colors']);
        } elseif (($_POST['type'] ?? '') == 'components') {
            // Drag and drop components
            $columns = [
                'left'  => $_POST['column1'],
                'right' => $_POST['column2'],
                'top'   => $_POST['column3']
            ];

            foreach ($columns as $position => $componentString) {
                $components[$position] = explode(',', $componentString);

                $instanceCollection = new Schoolbox\Collection();

                foreach ($components[$position] as $sequence => $componentID) {
                    if ($componentID) {
                        try {
                            $inst = Component_InstanceMapper::getInstance()->findById($componentID);
                            $inst->setSequence($sequence + 1); // because we prefer to not have 0s in the database
                            $inst->setPosition($position);
                            $instanceCollection->add($inst);
                        } catch (Schoolbox\Mapper\Exception $e) {
                        }
                    }
                }

                if ($instanceCollection->count()) {
                    Component_InstanceMapper::getInstance()->save($instanceCollection);
                }
            }

            // Render the component just moved, so it can be re-rendered
            if ($_POST['movedComponentId'] ?? false) {
                $instance         = $componentInstanceMapper->findByInstanceId($_POST['movedComponentId']);
                if (is_object($instance)) {
                    $cClass = $instance->getDefinition()->getClass();
                } else {
                    exit("<div class='alert alert-box'>Couldn't move component.<br>Component has been deleted</div>");
                }
                $factoryFactory   = new Component_FactoryFactory();
                $componentFactory = $factoryFactory->build($instance->getModule(), $page->getDi()->get('session')->get('user'), $page, true);
                $renderer         = new Component_Renderer();
                $eportfolioPage   = new Eportfolio_Page_Instance(
                    $page,
                    $eportfolioHandler->getPageInstance()->getOwner()->getId(),
                    $eportfolioHandler->getPageId(),
                    UserAccess::ACCESS_FULL
                );

                /** @var Component_Eportfolio_ControllerAbstract $component */
                $component = $componentFactory->buildComponent($instance);
                $renderer->renderEportfolioComponent($page, $eportfolioPage, $component);
            }
        } elseif (is_numeric($_POST['delete'] ?? false)) {
            // Delete the instance and its configuration

            $instance = $componentInstanceMapper->findById($_POST['delete']);
            $configs  = $componentConfigInstanceMapper->findByColumn('component_instance_id', $instance->getId());

            foreach ($configs as $c) {
                $componentConfigInstanceMapper->delete($c);
            }

            $componentInstanceMapper->delete($instance);
        } elseif (is_numeric($_POST['add'] ?? false) || is_numeric($_POST['reload'] ?? false)) {
            // Add new component to page and output render of component

            if (is_numeric($_POST['add'] ?? false)) {
                $component = new Component_Instance();
                $component
                    ->setDefinitionId($_POST['add'])
                    ->setIdentifier($_POST['page_id'])
                    ->setModule('eportfolio')
                    ->setSequence(0)
                    ->setPosition('top');

                $ids = $componentInstanceMapper->save($component);

                $instanceID = $ids[0];
            } else {
                $instanceID = $_POST['reload'];
            }

            $componentInstanceMapper->cascadeSequence($eportfolioHandler->getSettingsPageOwner());

            // Retrieve the instance definition controller
            $factory = new Component_Eportfolio_Factory(new User(), $page);

            $instance = $componentInstanceMapper->findByInstanceId($instanceID);

            /** @var Component_Eportfolio_ControllerAbstract $component */
            $component = $factory->buildComponent($instance);

            if (!($component instanceof Component_ControllerAbstract)) {
                // Something went wrong instantiating component, return an error message that will get displayed
                // in the components place

                die("
                    <h2>Error adding component</h2>
                    <p>Could not find component to instantiate.</p>
                ");
            }

            // If this is a new component create a config and transfer across some items from the default configuration

            if (is_numeric($_POST['add'] ?? false)) {
                $componentInstance       = $component->getInstance();
                $componentInstanceConfig = $componentInstance->getConfig();
                $componentDefinition     = $componentClass = $component->getInstance()->getDefinition();

                // Define configuration items to be copied

                // Always copy the component name

                $configurationToCopy = [
                    'componentname',
                ];

                // And any other fields in the component definition

                $configCopy = explode(",", $componentDefinition->getConfigCopy());

                if (!empty($configCopy)) {
                    array_walk($configCopy, 'trim');
                    $configCopy = array_unique($configCopy);

                    if (count($configCopy) > 0) {
                        $configurationToCopy = array_merge($configurationToCopy, $configCopy);
                    }
                }

                /** @var Component_ConfigDefinition $currentConfig */
                foreach ($componentInstance->getConfig() as $currentConfig) {
                    if (in_array($currentConfig->getKey(), $configurationToCopy)) {
                        try {
                            $configInstance = Component_ConfigMapper::getInstance()->findOneByColumnList([
                                'component_config_definition_id' => $currentConfig->getId(),
                                'component_instance_id'          => $_POST['from'],
                            ]);

                            $value = $configInstance->getValue();

                            $newConfigInstance = new Component_Config();
                            $newConfigInstance->setId(null);
                            $newConfigInstance->setConfigDefinitionId($currentConfig->getId());
                            $newConfigInstance->setInstanceId($componentInstance->getId());
                            $newConfigInstance->setValue($value);

                            Component_ConfigMapper::getInstance()->save($newConfigInstance);

                            // Update the configuration item on the component in memory so that it renders properly later

                            $component->setConfigValue($currentConfig->getKey(), $value);
                        } catch (MapperException $e) {
                            $value = $currentConfig->getDefaultValue();
                        }
                    }
                }
            }

            $eportfolioPage = new Eportfolio_Page_Instance(
                $page,
                $eportfolioHandler->getPageInstance()->getOwner()->getId(),
                $eportfolioHandler->getPageId(),
                UserAccess::ACCESS_FULL
            );

            $renderer = new Component_Renderer();
            $renderer->renderEportfolioComponent($page, $eportfolioPage, $component);
        } elseif (is_numeric($_POST['leftColWidth'] ?? false) && is_numeric($_POST['rightColWidth'] ?? false)) {
            // Update column widths

            $eportfolioHandler->updateGrid(Scrub::text($_POST['leftColWidth']));
        } elseif (isset($_POST['update']) && ($_POST['update'] == 'page')) {
            // Update permissions and page title

            EportfolioPermissionMapper::getInstance()->updatePermissions($eportfolioHandler->getPageId(), $_POST['parent_permission'], $_POST['group_permission'], $_POST['individual_permission']);
            $eportfolioHandler->updatePageTitle(trim($_POST['pageTitle']));
            SitePage::localRedir("/eportfolio/{$eportfolioHandler->getPageInstance()->getOwner()->getId()}/{$eportfolioHandler->getPageId()}");
        } elseif (isset($_POST['deletePage'])) {
            // Softdelete this page and redirect back to profile
            $eportfolioHandler->softdelete();
            SitePage::localRedir("/eportfolio/{$eportfolioHandler->getPageInstance()->getOwner()->getId()}/profile");
        } elseif (($_POST['type'] == 'config') && $_POST['component']) {
            // Update config options from homepage
            $config = $_POST;

            /** @var Component_Instance $instance */
            $instance                        = $componentInstanceMapper->findByInstanceId($config['component']);
            $componentConfigDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();

            $configDefinitionCollection = $componentConfigDefinitionMapper->findByColumn(
                'component_definition_id',
                $instance->getDefinitionId()
            );

            $pageId = $config['page_id'];

            unset($config['page_id'], $config['type'], $config['component']);

            // As much as I hate exceptional cases, there are exceptional cases...
            // Remove the prepended '/homepage/'

            if (isset($config['src'])) {
                preg_match("#[^0-9]*([0-9]*)#i", $config['src'], $matches);
                if ($matches) {
                    $config['src'] = $matches[1];
                }
            }

            // Prepare filter value

            if (isset($config['filter'])) {
                // Retrieve the instance so we know how to insert this data
                $cclass = $instance->getDefinition()->getClass();

                /** @var Component_ControllerAbstract $controller */
                $controller = new $cclass($instance, $page->getDi()->get('session')->get('user'), $page);
                $fields     = $controller->getFilterFields();
                $val        = 0;
                for ($i = 0; $i < count($fields); $i++) {
                    $add = isset($config['filter'][$fields[$i]]) && $config['filter'][$fields[$i]] ? 1 : 0;
                    $val += $add * pow(2, $i);
                }
                $config['filter'] = $val;
            }

            /** @var Component_ConfigDefinition $confD */
            foreach ($configDefinitionCollection as $confD) {
                if (isset($config[$confD->getKey()])) {
                    // This should be 1:1 relation, so return only the first instance of this definition

                    try {
                        $confI = $componentConfigInstanceMapper->findOneByColumnList([
                            'component_config_definition_id' => $confD->getId(),
                            'component_instance_id'          => $instance->getId()
                        ]);
                    } catch (MapperException $e) {
                        $confI = new Component_Config();
                        $confI->setConfigDefinitionId($confD->getId());
                        $confI->setInstanceId($instance->getId());
                    }

                    $confI->setValue($config[$confD->getKey()]);

                    $componentConfigInstanceMapper->save($confI);
                }
            }

            if (SitePage::detectAjax()) {
                // Just rebuild and refetch the component
                $factory = new Component_Eportfolio_Factory(new User(), $page);

                $componentInstance = $componentInstanceMapper->findByInstanceId($instance->getId());

                /** @var Component_Eportfolio_ControllerAbstract $component */
                $component = $factory->buildComponent($componentInstance);
                $renderer  = new Component_Renderer();

                $eportfolioPage = new Eportfolio_Page_Instance(
                    $page,
                    $eportfolioHandler->getPageInstance()->getOwner()->getId(),
                    $eportfolioHandler->getPageId(),
                    UserAccess::ACCESS_FULL
                );

                $renderer->renderEportfolioComponent($page, $eportfolioPage, $component);
            } else {
                SitePage::localRedir("/eportfolio/{$eportfolioHandler->getPageInstance()->getOwner()->getId()}/{$eportfolioHandler->getPageId()}");
            }
        }
    } else {
        error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }
} elseif (isset($_POST['page_title']) && strlen(trim($_POST['page_title'])) && isset($_POST['page_template']) && is_numeric($_POST['page_template'])) {
    // Create page for the currently logged-in user

    $userId     = $_POST['userId'];
    $pageId     = null;
    $pageTitle  = $_POST['page_title'];
    $templateId = $_POST['page_template'];

    try {
        $owner = \Schoolbox\UserMapper::getInstance()->findById($userId);
    } catch (\Schoolbox\Mapper\Exception $e) {
        error(ErrorMapper::ERROR_INFORMATION_MISSING);
        exit();
    }

    $authenticator = new Eportfolio_Authenticator($_SESSION['user'], $owner);

    if ($authenticator->canCreate()) {
        // Create page and apply components (a page_id is generated)

        $eportfolioHandler = new Eportfolio($userId, $pageId);
        $eportfolioHandler->createEportfolioFromTemplate($pageTitle, $templateId);

        // Apply permissions to the newly-created page

        Eportfolio\PermissionMapper::getInstance()->updatePermissions($eportfolioHandler->getPageId(), $_POST['parent_permission'], $_POST['group_permission'], $_POST['individual_permission']);

        // Redirect to newly created page

        SitePage::localRedir("/eportfolio/{$eportfolioHandler->getUserId()}/{$eportfolioHandler->getPageId()}");
    } else {
        error(ErrorMapper::ERROR_PERM_PAGE_VIEW);
    }
} else {
    error(ErrorMapper::ERROR_INFORMATION_MISSING);
}
