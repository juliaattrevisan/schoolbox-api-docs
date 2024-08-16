<?php

// The kernel contains the dependency injection engine.
// If you inject the dependency injection engine into Sitepage,
// it can use Twig to generate component content.

use Schoolbox\Application\WebRequest\Kernel;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

/** @var Kernel $kernel */
require_once __DIR__ . '/../../../conf/core/require.php';
$kernel = new Kernel();

/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

/** @var User $user */
$user = $_SESSION['user'];

if (!($user->isSuperuser())) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

$groupHandler = new Group(UserAccess::SERVICE_HOMEPAGE);

if (!($user->isSuperuser())) {
    throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
}

$cid = is_numeric($_POST['cid'] ?? false) ? (int)$_POST['cid'] : null;

$componentInstanceMapper       = Component_InstanceMapper::getInstance();
$componentConfigInstanceMapper = Component_ConfigMapper::getInstance();

if (($_POST['type'] ?? '') == 'components') {
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

    // Was any one component requested?
    // If so render it only so it can be re-rendered
    if ($_POST['movedComponentId'] ?? false) {
        $instance = $componentInstanceMapper->findByInstanceId($_POST['movedComponentId'], $cid);
        if (is_object($instance)) {
            $page->outputJson(['hasComponentBeenDeleted' => false], false, 200);
        } else {
            $page->outputJson(['hasComponentBeenDeleted' => true], false, 200);
        }
    }
} elseif (is_numeric($_POST['delete'] ?? false)) {
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
} elseif ($cid && (is_numeric($_POST['add'] ?? null) || is_numeric($_POST['reload'] ?? null))) {
    // POST new component to page and output render of component
    if (is_numeric($_POST['add'])) {
        $componentDefinitionId = (int)$_POST['add'];

        $newComp = new Component_Instance();
        $newComp
            ->setDefinitionId($componentDefinitionId)
            ->setIdentifier($cid)
            ->setModule($_POST['context'])
            ->setSequence(0)
            ->setPosition('top');

        $ids        = $componentInstanceMapper->save($newComp);
        $instanceID = $ids[0];

        $componentConfigInstanceMapper->injectDefaultConfigCollection($instanceID, $componentDefinitionId);
        $componentInstanceMapper->cascadeSequence($cid);
    } else {
        $instanceID = $_POST['reload'];
    }

    $factoryFactory = new Component_FactoryFactory();
    $factory        = $factoryFactory->build($_POST['context'], $page->getDi()->get('session')->get('user'), $page, true);

    // $component instanceof Component_ControllerAbstract
    $instance = $componentInstanceMapper->findByInstanceId($instanceID, $cid);
    if (null === $instance) {
        throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
    }
    $component = $factory->buildComponent($instance);

    if (!($component instanceof Component_ControllerAbstract)) {
        die("<h2>Error adding component</h2><p>Could not find component to instantiate.</p>");
    }
    $renderer       = new Component_Renderer();
    $renderer->renderAdminComponent($page, $component);
    $page->outputPage(true);
} elseif ($cid && $_POST['type'] == 'config' && $_POST['component']) {
    // POST config options from homepage
    $config = $_POST;

    /** @var Component_Instance|null $instance */
    $instance = $componentInstanceMapper->findByInstanceId($config['component'], $cid);

    if (is_object($instance)) {
        $componentConfigDefinitionMapper = Component_ConfigDefinitionMapper::getInstance();

        $configDefinitionCollection = $componentConfigDefinitionMapper->findByColumn(
            'component_definition_id',
            $instance->getDefinitionId()
        );

        unset($config['cid'], $config['type'], $config['component']);

        // Create a controller object for the config class to use when
        // dehydrating config values into database
        $factoryFactory   = new Component_FactoryFactory();
        $componentFactory = $factoryFactory->build($instance->getModule(), $page->getDi()->get('session')->get('user'), $page, true);
        $controller       = $componentFactory->buildComponent($instance);

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

                $componentConfigInstanceMapper->save($confI);
            }
        }
        if (SitePage::detectAjax()) {
            // Just refetch the instance
            $instance         = $componentInstanceMapper->findByInstanceId($instance->getId(), $cid);
            if (null === $instance) {
                throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }
            $factoryFactory   = new Component_FactoryFactory();
            $componentFactory = $factoryFactory->build($instance->getModule(), $page->getDi()->get('session')->get('user'), $page, true);
            $renderer         = new Component_Renderer();
            $renderer->renderAdminComponent($page, $componentFactory->buildComponent($instance));
            $page->outputPage(true);
            exit;
        }
    } else {
        $page->outputJson('error: component does not exist', false, 409);
    }
}
