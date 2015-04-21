<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// require SitePage context
if (!isset($this)) {
    require_once('../../../include/Bootstrap.php');
    httpError(400, 101); // resource not available
}

$this->setAjax(true);

$method = strtoupper($_SERVER['REQUEST_METHOD']);

// check authorised
if (!isset($_SESSION['id'])) {
    httpError(401, 1);
}


// retrieve the config for the specified component
if ($method == 'GET') {
    checkFields($_GET);
    readConfig($_GET, $this);
} // update the config for the specified component
elseif ($method == 'POST') {
    checkFields($_POST);
    writeConfig($_POST, $this);
} else {
    // method not recognised
    httpError(405, 'Invalid method: should be GET or POST.');
}


/**
 * @param $vars
 * @param SitePage $page
 */
function readConfig($vars, SitePage $page)
{
    $groupHandler = new Group();
    // superuser check precedes group perm, so if categoryID was passed as a templateID, it won't cause httpErrorure
    if (!$groupHandler->isSuperuser() && !$groupHandler->getPermission($vars['cid'])) {
        // lack permission to see configuration details
        httpError(403, 6);
    }

    // get the component
    $cim = Component_InstanceMapper::getInstance();
    $chf = new Component_Homepage_Factory(new \Schoolbox\User(), $page, true);

    $instance = array_shift($cim->findByInstanceId($vars['component']));

    $component = $chf->buildComponent($instance);

    // it's in an array, but there's only going to be one
    if ($component === false) {
        httpError(400, "Component doesn't exist.");
    }

    $component->setup(null, $component->getInstance()->getConfigArray());
    $component->parseConfigForTemplate();

    $page->setVar(array(
        'ID'                        => $vars['cid'],
        'SITE_LOCATION'             => Cfg::get('site_location'),
        'COMPONENT_INSTANCE_ID'     => $component->getInstance()->getId(),
        'COMPONENT_HANDLE'          => $component->getInstance()->getId(),
        'component.instance.id'     => $component->getInstance()->getId(),
        'component.definition.id'   => $component->getInstance()->getDefinition()->getId(),
        'component.definition.name' => $component->getInstance()->getDefinition()->getName(),
    ));

    $page->template->pparse('CONFIGURATION', 'configTpl');
}


/**
 * @param $vars
 */
function writeConfig($vars, SitePage $page)
{
    $groupHandler = new Group();
    if (!$groupHandler->getPermission($vars['cid'])) {
        // lack permission to see configuration details
        httpError(403, 6);
    }

    $componentID = $vars['component'];
    unset($vars['cid']);
    unset($vars['component']);

    // vars is now the target configuration

    $cim = Component_InstanceMapper::getInstance();

    /** @var Component_Instance $instance */
    $instance = reset($cim->findByInstanceId($componentID));
    $ccdm = Component_ConfigDefinitionMapper::getInstance();

    $cdm = $ccdm->findByColumn('component_definition_id', $instance->getDefinitionId());

    $ccm = Component_ConfigMapper::getInstance();

    // Create a controller object for the config class to use when dehydrating
    // config values
    $factory    = new Component_Dashboard_Factory(new \Schoolbox\User(), $page);
    $controller = $factory->buildComponent($instance);

    /** @var Component_ConfigDefinition $confD */
    foreach ($cdm as $confD) {
        $key = $confD->getKey();
        if (isset($vars[$key])) {
            $configItem = Component_Config_Factory::build($key, array(), $vars);
            $value = $configItem->dehydrate($vars[$key], $controller);

            // this should be 1:1 relation, so return only the first instance of this definition
            /** @var Component_Config $confI */
            $confI = $ccm->findByColumnList(array(
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
            $confI->setValue($value);

            $ccm->save($confI);

        }
    }
    exit;
}


function checkFields($array)
{
    // mandatory arguments
    $reqd = array('component', 'cid');
    foreach ($reqd as $field) {
        if (!isset($array[$field])) {
            // necessary information is lacking
            httpError(400, 2);
        }
    }
    return true;
}
