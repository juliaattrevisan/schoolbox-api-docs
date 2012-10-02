<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

// no SitePage context allowed
if(isset($this)) {
    error(19);
}

require_once('../../include/Bootstrap.php');
$method = strtoupper($_SERVER['REQUEST_METHOD']);

// create session context
new SitePage();

// check authorised
if(!isset($_SESSION['id'])) {
    fail(401, 1);
}


// retrieve the config for the specified component
if($method == 'GET') {
    checkFields($_GET);
    readConfig($_GET);
}
// update the config for the specified component
elseif($method == 'POST') {
    checkFields($_POST);
    writeConfig($_POST);
}
else {
    // method not recognised
    fail(405, 'Invalid method: should be GET or POST.');
}


/**
 * @param $vars
 */
function readConfig($vars) {
    $page = new SitePage(false);
    $groupHandler = new Group();
    // superuser check precedes group perm, so if categoryID was passed as a templateID, it won't cause failure
    if(!$groupHandler->isSuperuser() && !$groupHandler->getPermission($vars['cid'])) {
        // lack permission to see configuration details
        fail(403, 6);
    }

    // get the component
    $cim = Component_InstanceMapper::getInstance();
    $chf = new Component_Homepage_Factory(new User(), $page, true);

    $instance = array_shift($cim->findByInstanceId($vars['component']));

    $component = $chf->buildComponent($instance);

    // it's in an array, but there's only going to be one
    if($component === false) {
        fail(400, "Component doesn't exist.");
    }

    $component->setup(array(), $component->instance->getConfigArray());
    $component->execute();
    $component->parseConfig();

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
        fail(403, 6);
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
            ))->current();

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
            fail(400, 2);
        }
    }
    return true;
}

/**
 * @param $code
 * @param $message
 */
function fail($code, $message) {
    if(is_numeric($message)) {
        $msg = Database::getInstance()->query("SELECT message FROM error WHERE id = $message")->fetchColumn();
    }
    else {
        $msg = $message;
    }
    $codes = array(
        '400' => 'Bad Request',
        '401' => 'Unauthorized',
        '403' => 'Forbidden',
        '405' => 'Method Not Allowed',
    );
    if(isset($codes[$code])) {
        header('HTTP/1.1 '.$code.' '.$codes[$code]);
    }
    else {
        header('HTTP/1.1 400 Bad Request');
    }
    die($msg);
}