<?php
/**
 * !IMPORTANT! DEPENDENCY: SQL SCRIPT TO MODIFY SCHEMA OF TABLES USED IN THIS SCRIPT
 * This script takes existing component definitions in `homepage_comp` and refactors into `component_definition`
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/**
 * opts:
 * --status    get the current status of component refactoring
 * --skip-sql  don't perform the SQL insertion/update
 * -v          verbose output
 */
$options = getopt("v", array('status', 'skip-sql'));

require_once('../../../include/Bootstrap.php');

class DashboardComponentReFactory {

    private $classBase = "Component_Homepage_%sController";

    private $labelMappings = array(
        'size'          => 'Limit',
        'componentname' => 'Heading',
        'src'           => 'Source',
        'pixel'         => 'Max width',
        'sort'          => 'Sort by',
        'sortdir'       => 'Direction',
        'comments_size' => 'Number of comments'
    );

    private $customConfigs = array(
        'Assess' => array('compactMode' => 'false'),
    );

    private $newConfigDefinitions = array();

    /**
     * @var Database
     */
    private $db;

    /**
     * @var bool
     */
    private $beVerbose;


    /**
     * @param array $options
     */
    public function __construct($options) {

        $this->beVerbose = isset($options['v']);
        $this->db = Database::getInstance();

        if(isset($options['status'])) {
            $this->printStatus();
        }
        else {
            $this->execute(!isset($options['skip-sql']));
        }
    }


    /**
     * Compile a list of components that have and have not yet been converted
     */
    public function printStatus() {

        $comp = array();

        // get list of old components
        $origin = $this->db->query("SELECT * FROM `homepage_comp`");
        while($r = $origin->fetch(PDO::FETCH_ASSOC)) {
            $comp['old'][] = $r;
            // convert old list to new controller classnames
            $comp['new'][] = array('class' => $this->method2controller($r['method']));
        }

        // test the existence of new controller names in database
        $source = $this->db->query("SELECT `id`, `class` FROM `component_definition` WHERE `module` = 'homepage'");
        $registeredControllers = array();
        while($r = $source->fetch(PDO::FETCH_ASSOC)) {
            $registeredControllers[$r['class']] = $r['id'];
        }

        $source = $this->db->query("SELECT * FROM `component_config_definition`");
        $registeredConfigs = array();
        while($r = $source->fetch(PDO::FETCH_ASSOC)) {
            $registeredConfigs[$r['component_definition_id']][] = $r;
        }

        foreach($comp['new'] as &$nc) {
            if(in_array($nc['class'], array_keys($registeredControllers))) {
                $nc['registered'] = true;

                // it's registered, so we'll assume its configs are registered as well

                if(isset($registeredConfigs[$registeredControllers[$nc['class']]])) {
                    foreach($registeredConfigs[$registeredControllers[$nc['class']]] as $conf) {
                        print_r($conf);
                    }
                }
                else {
                    echo "Missing configs for {$nc['class']}\n";
                }


            }
            else {
                $nc['registered'] = false;
            }

            // test the existence of new controller classes
            if(class_exists($nc['class'])) {
                $nc['exists'] = true;
            }
            else {
                $nc['exists'] = false;
            }
        }

        printf("\n%23s    %-45s%-8s%s\n",'method','controller','reg','file');
        for($i = 0; $i < 100; $i++) echo "-"; echo "\n";
        for($i = 0; $i < count($comp['old']); ++$i) {
            printf(" [%s] %18s => %-42s :  %-8s%s\n",
                ($comp['new'][$i]['registered'] && $comp['new'][$i]['exists']) ? ' ' : '!',
                $comp['old'][$i]['method'],
                $comp['new'][$i]['class'],
                $comp['new'][$i]['registered'] ? 'Y' : 'N',
                $comp['new'][$i]['exists'] ? 'Y' : 'N'
            );
            // if the component is registered, display the status of its config template files
            if($comp['new'][$i]['registered']) {

            }
        }
        echo "\n";

    }


    /**
     * @param bool $performSQL
     */
    public function execute($performSQL) {

        $dataset = $this->db->query("SELECT * FROM `component_config_definition`")->fetchAll(PDO::FETCH_ASSOC);
        $existingConfigDefinitions = array();
        foreach($dataset as $d) {
            $existingConfigDefinitions[$d['key']] = $d;
        }
        unset($dataset);

        // get the highest index of definitions
        $i = $this->db->query("SELECT `id` FROM `component_definition` ORDER BY `id` DESC")->fetchColumn();

        $newDefinitions = array();
        $newConfigDefinitions = array();

        $origin = $this->db->query("SELECT * FROM `homepage_comp`");

        while($oldComp = $origin->fetch(PDO::FETCH_ASSOC)) {
            $newComp = new Component_Definition();

            $newComp->id = ++$i;
            $newComp->name = $oldComp['name'];

            if(($newComp->class = $this->method2controller($oldComp['method'])) === false) {
                continue;
            }

            $newComp->description = $oldComp['description'];
            $newComp->module = 'homepage';
            $newComp->componentCategory = $oldComp['homepageCategoryID'];
            $newComp->visible = $oldComp['invisible'] == 'true' ? 'false' : 'true';
            $newComp->multiple = $oldComp['allowMultiple'];

            /* config definitions and instances */
            $this->parseSerializedConfig($oldComp['config'], $newComp);

            // custom cases for links, files and folders because their sources aren't defined in the default config
            if(in_array($oldComp['method'], array('outputLinkList', 'outputFileList','outputFolderList', 'outputNews'))) {
                $confDefinition = new Component_ConfigDefinition();
                $confDefinition->id           = null;
                $confDefinition->definitionId = $newComp->id;
                $confDefinition->key          = 'src';
                $confDefinition->label        = 'Source';
                $confDefinition->defaultValue = '';
                $this->newConfigDefinitions[] = $confDefinition;
            }

            $newDefinitions[] = $newComp;

        }

        if($performSQL) {
            Component_DefinitionMapper::getInstance()->save($newDefinitions);
            Component_ConfigDefinitionMapper::getInstance()->save($this->newConfigDefinitions);
        }

        if($this->beVerbose) {
            foreach($newDefinitions as $def) {
                printf("%s [ %s%s ]\n",$def->name, $def->class, class_exists($def->class, true) ? '' : ' (does not exist)');
                foreach($this->newConfigDefinitions as $conf) {
                    if($conf->definitionId == $def->id) {
                        printf(" > %s :%s\n", $conf->key, $conf->defaultValue);
                    }
                }
                echo "\n";
            }
        }
    }

    /**
     * @param string $configString
     * @param Component_Definition $newComp
     */
    public function parseSerializedConfig($configString, $newComp = null, $return = false) {
        $oldCompConfig = unserialize($configString);

        $rtn = array();

        if($newComp) {
            $oldCompConfig = $this->mergeCustomConfig($newComp, $oldCompConfig);
        }

        if(is_array($oldCompConfig) && count($oldCompConfig)) {
            foreach($oldCompConfig as $key => $value) {

                if(preg_match('#homepage-.*#i', $key)) {
                    $key = 'src';
                }

                // keys can be duplicated, but the combination of key and definitionId must be unique
                // database schema will perform update if the combination is not unique
                $confDefinition = new Component_ConfigDefinition();
                $confDefinition->id = null;
                $confDefinition->key = $key;

                if($newComp) {
                    $confDefinition->definitionId = $newComp->id;
                }

                if(($confDefinition->defaultValue = $this->refactorDefaultValue($key, $value)) === false) {
                    continue;
                }

                $confDefinition->label = $this->refactorLabel($key);

                if($return) {
                    $rtn[] = $confDefinition;
                }
                else {
                    $this->newConfigDefinitions[] = $confDefinition;
                }

            }
        }

        if($return) {
            return $rtn;
        }
    }

    /**
     * @param $newComp
     * @param $oldCompConfig
     * @return array
     */
    public function mergeCustomConfig($newComp, $oldCompConfig) {
        // merge any configurations that we come across that weren't specified in the db into the old config array

        $k = preg_replace("#Homepage_([A-Za-z]*)Controller#i", "$1", $newComp->class);
        if(isset($this->customConfigs[$k])) {
            if(!is_array($oldCompConfig)) {
                $oldCompConfig = array();
            }
            return array_merge($oldCompConfig, $this->customConfigs[$k]);
        }
        return $oldCompConfig;
    }


    /**
     * @param string $method
     * @return string|bool
     */
    public function method2controller($method) {

        // standard component
        if(strpos($method, 'output') !== false) {
            $method = substr($method, 6);
        }

        // mandatory non-customisable components
        elseif($method == 'initSubject') {
            return false;
        }
        elseif($method == 'folderManage') {
            return false;
        }
        else {
            echo "[!] Couldn't map method $method.\n";
            return false;
        }

        return sprintf($this->classBase, $method);
    }


    /**
     * @param $value
     * @return mixed
     */
    public function refactorDefaultValue($key, $value) {
        if(is_array($value)) {
            // exceptional case: array of booleans to be stored as array
            if($key == 'filter') {
                $v = pow(2, count($value)) - 1;
            }
            // ignore all other cases (only other known is filterOptions)
            else {
                return false;
            }
        }
        else {
            $v = $value;
        }

        return $v;

    }

    /**
     * @param $key
     * @return string
     */
    public function refactorLabel($key) {
        // cases for handling the labels
        if(isset($labelMappings[$key])) {
            return $labelMappings[$key];
        }
        elseif(strpos($key,'_') !== false) {
            return ucwords(str_replace('_', ' ', $key));
        }
        else {
            // by default, make camel-case human friendly
            return ucwords(preg_replace('#[A-Z][^A-Z]#sm',' $0', $key));
        }
    }


}

new DashboardComponentReFactory($options);


/* Original `homepage_comp` schema

id                  : prim
size                : deprecated
method              : refactor to controller
name                : 1:1
description         : 1:1
homepageCategoryID  : 1:1
filename            : deprecated
config              : refactor to config table
invisible           : 1!:1
allowMultiple       : 1:1
 */


/* Target `component_definition` schema

id                  : prim
name                : 1:1
class               : mapped from method
description         : 1:1
module              : 'homepage'
component_category  : 1:1
visible             : inverse of invisible
multiple            : 1:1

 */

