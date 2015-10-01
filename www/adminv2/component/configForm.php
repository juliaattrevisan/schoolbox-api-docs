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
    // GET config options from homepage
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
        if (SitePage::detectAjax()) {
            // Render all of the components, and let Marilyn Masonry figure it out
            $page->setFile(TEMPLATE_body, 'adminv2/component/layout.html');
            $page->setBlock(TEMPLATE_body, 'position_top', 'position_top_block');
            $page->setBlock(TEMPLATE_body, 'position_left', 'position_left_block');
            $page->setBlock(TEMPLATE_body, 'position_right', 'position_right_block');

            $componentFactory = buildComponentFactory('homepage', new Schoolbox\User(), $page);

            $componentCollection = $componentFactory->buildComponentList($_GET['cid']);

            $occupiedPositionList = array();

            foreach ($componentCollection as $component) {
                /** @var Component_ControllerAbstract $component */
                $instance_no = $component->getInstance()->getId();
                $position_no = $component->getInstance()->getPosition();
                $position    = "position_{$position_no}";

                if (!$page->template->exists("{$position}_block")) {
                    $page->setFile($position, 'adminv2/component/placeholder.html');
                }

                $component->setup(null, array());

                $page->setVar(array(
                    'COMPONENT_INSTANCE_ID'     => $instance_no,
                    'COMPONENT_HANDLE'          => $instance_no,
                    'component.instance.id'     => $instance_no,
                    'component.definition.id'   => $component->getInstance()->getDefinition()->getId(),
                    'component.definition.name' => $component->getInstance()->getDefinition()->getName(),
                ));

                $component->setConfigurationTemplate('component/config/_templatewrapper.html');
                $component->parseConfigForTemplate();

                $page->parse("{$position}_block", $position, true);

                if (!in_array($position_no, $occupiedPositionList)) {
                    $occupiedPositionList[] = $position_no;
                }
            }

            $emptyPositionList = array_diff(array('top', 'left', 'right'), $occupiedPositionList);

            foreach ($emptyPositionList as $emptyPosition) {
                $page->setVar("position_{$emptyPosition}_block", '');
            }
            $page->outputPage(true);
            exit;
        } else {
//            SitePage::localRedir("/homepage/" . $_GET['cid']);
        }
    }
//    SitePage::localRedir("/homepage/" . $_GET['cid']);
}

/**
 * Factory function to produce...uh, a Factory -_-
 * @param $context
 * @param Schoolbox\User $user
 * @param SitePage $sitepage
 * @return Component_FactoryAbstract|null
 */
function buildComponentFactory($context, Schoolbox\User $user, SitePage $sitepage)
{
    $contextMap = array(
        'dashboard'  => false,
        'homepage'   => true,
        'eportfolio' => true
    );

    if (isset($contextMap[$context])) {
        $class = sprintf('Component_%s_Factory', ucfirst(strtolower($context)));

        if ($contextMap[$context]) {
            return new $class($user, $sitepage, true);
        } else {
            return new $class($user, $sitepage);
        }
    }
    return null;
}
