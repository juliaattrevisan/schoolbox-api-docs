<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// require SitePage context
if(!isset($this)) {
    require_once('../../../include/Bootstrap.php');
    httpError(400, 101); // resource not available
}

$this->setAjax(true);

$method = strtoupper($_SERVER['REQUEST_METHOD']);

// check authorised
if(!isset($_SESSION['id'])) {
    httpError(401, 1);
}


// retrieve the config for the specified component
if($method == 'GET') {
    checkFields($_GET);
    readConfig($_GET, $this);
}
// update the config for the specified component
elseif($method == 'POST') {
    checkFields($_POST);
    writeConfig($_POST);
}
else {
    // method not recognised
    httpError(405, 'Invalid method: should be GET or POST.');
}


/**
 * @param $vars
 * @param SitePage $page
 */
function readConfig($vars, SitePage $page) {
    $groupHandler = new Group();
    // superuser check precedes group perm, so if categoryID was passed as a templateID, it won't cause httpErrorure
    if(!$groupHandler->isSuperuser() && !$groupHandler->getPermission($vars['cid'])) {
        // lack permission to see configuration details
        httpError(403, 6);
    }

    // get the component
    $cim = Component_InstanceMapper::getInstance();
    $chf = new Component_Homepage_Factory(new User(), $page, true);

    $instance = array_shift($cim->findByInstanceId($vars['component']));

    $component = $chf->buildComponent($instance);

    // it's in an array, but there's only going to be one
    if($component === false) {
        httpError(400, "Component doesn't exist.");
    }

    $component->setup(
        array('id' => '-1', 'code' => '', 'name' => ''),
        $component->instance->getConfigArray()
    );
    $component->execute();
    $component->parseConfigForTemplate();

    $page->setVar(array(
        'ID'                        => $vars['cid'],
        'SITE_LOCATION'             => Cfg::get('site_location'),
        'COMPONENT_INSTANCE_ID'     => $component->instance->id,
        'COMPONENT_HANDLE'          => $component->instance->id,
        'component.instance.id'     => $component->instance->id,
        'component.definition.id'   => $component->definition->id,
        'component.definition.name' => $component->definition->name,
    ));

    $page->template->pparse('CONFIGURATION', 'configTpl');
}


/**
 * @param $vars
 */
function writeConfig($vars) {
    $groupHandler = new Group();
    if(!$groupHandler->getPermission($vars['cid'])) {
        // lack permission to see configuration details
        httpError(403, 6);
    }

    $componentID = $vars['component'];
    unset($vars['cid']);
    unset($vars['component']);

    // vars is now the target configuration

    $cim = Component_InstanceMapper::getInstance();

    $instance = $cim->findById($componentID);
    $ccdm = Component_ConfigDefinitionMapper::getInstance();

    $cdm = $ccdm->findByColumn('component_definition_id', $instance->definitionId);

    // much as I hate exceptional cases, this is an exceptional case: remove the prepended '/homepage/'
    if(isset($vars['src'])) {
        preg_match("#[^0-9]*([0-9]*)#i", $vars['src'], $matches);
        if($matches) {
            $vars['src'] = $matches[1];
        }
    }

    $ccm = Component_ConfigMapper::getInstance();
    $updated = 0;
    foreach($cdm as $confD) {
        if(isset($vars[$confD->key])) {
            ++$updated;

            // this should be 1:1 relation, so return only the first instance of this definition
            $confI = $ccm->findByColumnList(array(
                'component_config_definition_id' => $confD->id,
                'component_instance_id'          => $instance->id
            ))->first();

            printf("Changing value of key `%s` from '%s' to '%s'<br/>\n", $confD->key, $confI->value, $vars[$confD->key]);

            if(!($confI->id)) {
                $confI = new Component_Config();
                $confI->configDefinitionId = $confD->id;
                $confI->instanceId = $instance->id;
            }
            $confI->value = $vars[$confD->key];

            $ccm->save($confI);

        }
    }
    die($updated ? "Configuration: $updated updated." : 'No configuration change.');

}


function checkFields($array) {
    // mandatory arguments
    $reqd = array('component', 'cid');
    foreach($reqd as $field) {
        if(!isset($array[$field])) {
            // necessary information is lacking
            httpError(400, 2);
        }
    }
    return true;
}
