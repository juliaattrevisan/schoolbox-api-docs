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
        $source = $this->db->query("SELECT `class` FROM `component_definition` WHERE `module` = 'homepage'");
        $registeredControllers = array();
        while($r = $source->fetchColumn(0)) {
            $registeredControllers[] = $r;
        }

        foreach($comp['new'] as &$nc) {
            if(in_array($nc['class'], $registeredControllers)) {
                $nc['registered'] = true;
            }
            else {
                $nc['registered'] = false;
            }

            // test the existence of new controller files
            if(file_exists((dirname(__FILE__).'/../../../library/'.str_replace('_','/',$nc['class'])).'.php')) {
                $nc['exists'] = true;
            }
            else {
                $nc['exists'] = false;
            }
        }

        for($i = 0; $i < count($comp['old']); ++$i) {
            printf(" [%s] %18s => %-42s : %s, %s\n",
                ($comp['new'][$i]['registered'] && $comp['new'][$i]['exists']) ? ' ' : '!',
                $comp['old'][$i]['method'],
                $comp['new'][$i]['class'],
                $comp['new'][$i]['registered'] ? 'registered' : 'not registered',
                $comp['new'][$i]['exists'] ? 'class exists' : 'class does not exist'
            );
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

            $oldCompConfig = unserialize($oldComp['config']);

            if(is_array($oldCompConfig) && count($oldCompConfig)) {
                foreach($oldCompConfig as $key => $value) {

                    if(preg_match('#homepage-.*#i', $key)) {
                        $key = 'src';
                    }

                    // keys can be duplicated, but the combination of key and definitionId must be unique
                    // database schema will perform update if the combination is not unique
                    $confDefinition = new Component_ConfigDefinition();
                    $confDefinition->id = null;
                    $confDefinition->definitionId = $newComp->id;
                    $confDefinition->key = $key;

                    if(($confDefinition->defaultValue = $this->refactorDefaultValue($key, $value)) === false) {
                        continue;
                    }

                    $confDefinition->label = $this->refactorLabel($key);

                    // for debug, it won't attempt to insert this value
                    $confDefinition['componentClassname'] = $newComp->class;

                    $newConfigDefinitions[] = $confDefinition;

                }
            }

            // custom cases for links, files and folders because their sources aren't defined in the default config
            if(in_array($oldComp['method'], array('outputLinkList', 'outputFileList','outputFolderList', 'outputNews'))) {
                $confDefinition = new Component_ConfigDefinition();
                $confDefinition->id           = null;
                $confDefinition->definitionId = $newComp->id;
                $confDefinition->key          = 'src';
                $confDefinition->label        = 'Source';
                $confDefinition->defaultValue = '';
                $newConfigDefinitions[] = $confDefinition;
            }

            $newDefinitions[] = $newComp;

        }

        if($performSQL) {
            Component_DefinitionMapper::getInstance()->save($newDefinitions);
            Component_ConfigDefinitionMapper::getInstance()->save($newConfigDefinitions);
        }

        if($this->beVerbose) {
            foreach($newDefinitions as $def) {
                printf("%s [ %s%s ]\n",$def->name, $def->class, class_exists($def->class, true) ? '' : ' (does not exist)');
                foreach($newConfigDefinitions as $conf) {
                    if($conf->definitionId == $def->id) {
                        printf(" > %s :%s\n", $conf->key, $conf->defaultValue);
                    }
                }
                echo "\n";
            }
        }
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

die();


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

