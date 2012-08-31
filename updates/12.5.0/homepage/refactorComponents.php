<?php
/**
 * !IMPORTANT! DEPENDENCY: SQL SCRIPT TO MODIFY SCHEMA OF TABLES USED IN THIS SCRIPT (refactorComments.sql)
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
$options = getopt("v", array('status', 'skip-sql', 'create-components', 'skip-clean-db', 'skip-instantiation'));
require_once('../../../include/Bootstrap.php');

class DashboardComponentReFactory {

    /**
     * Standard format for component names
     * @var string
     */
    private $classBase = "Component_Homepage_%sController";

    /**
     * Custom remappings of config labels to human-readable form.
     * A blank label indicates a config component that should not be displayed
     * @var array
     */
    private $labelMappings = array(
        'size'              => 'Limit',
        'componentname'     => 'Heading',
        'src'               => 'Source',
        'pixel'             => 'Max width / height',
        'sort'              => 'Sort by',
        'sortdir'           => 'Direction',
        'comments_size'     => 'Number of comments',
        'username'          => 'Author',
        'keywords'          => 'Hashtags',
        'teacher_timestamp' => '',
        'muted_users'       => '',
        'captionState'      => 'Captions',
        'displayPortraits'  => 'Display Portraits',
    );

    /**
     * These configurations will be appended to those in the database
     * Because the previous system didn't necessitate specifying config variables by default, there are
     * quite a number of "obvious" configs listed here.
     * @var array
     */
    private $customConfigs = array(
        'Assess'     => array(
            'compactMode' => 'false'
        ),
        'ForumList'  => array(
            'componentname' => 'Forum',
            'compactMode'   => 'false',
            'src'           => '',
        ),
        'FileList'   => array(
            'componentname' => 'Files',
            'compactMode'   => 'false',
            'src'           => '',
        ),
        'RSS'        => array(
            'compactMode' => 'false'
        ),
        'Survey'     => array(
            'compactMode' => 'false'
        ),
        'LinkList'   => array(
            'compactMode' => 'false',
            'src' => ''
        ),
        'News'       => array(
            'src'  => '',
        ),
        'Podcast'    => array(
            'compactMode' => 'false'
        ),
        'DueWorkList' => array(
            'compactMode' => 'false',
        ),
        'Scroller'    => array(
            'componentname' => 'Image Scroller',
            'src'           => '',
            'captionState'  => 'on',
            'timing'        => '2',
        ),
        'Chat'        => array(
            'muted_users'   => 'a:0:{}',
        ),
        'ClassList'   => array(
            'displayPortraits' => ''
        ),
        'Managers'    => array(
            'displayPortraits' => ''
        ),
        'Members'     => array(
            'displayPortraits' => ''
        ),
        'Teachers'    => array(
            'displayPortraits' => ''
        )
    );

    /**
     * Controller classnames in this list will have their visibility forcibly set to false
     * @var array
     */
    private $disableByDefault = array(
        'Component_Homepage_DutiesController',
        'Component_Homepage_MGGSHeaderController'
    );

    /**
     * This is regex: ensure the keys are mutually exclusive!
     * @var array
     */
    private $configKeyRemappings = array(
        '#(homepage|import)-.*#is'  => 'src',
        '#pollID#s'                 => 'poll',
        '#textBoxID#s'              => 'textbox',
        '#maxtweets#s'              => 'size',
    );

    /**
     * Mapping for handlers with nonstandard names
     * @var array
     */
    private $customControllers = array(
        'initSubject'  => 'ManageClass',
        'folderManage' => 'ManageFolder',
    );

    private $customNames = array(
        'Component_Homepage_ScrollerController' => 'Image Slideshow',
    );

    private $customDescriptions = array(
        'Component_Homepage_ManagersController' => 'Displays a list of group members with write access to the homepage. Useful in larger groups to show who manages the group.',
    );





    /* EVERYTHING ABOVE THIS POINT IS CONFIGURATION
       --------------------------------------------
       EVERYTHING BELOW THIS POINT IS LOGIC        */




    /**
     * New configuration definitions will be loaded into here for the sake of scope
     * @var array
     */
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

        if($this->beVerbose) {
            printf("\n **********\n * Refactor Homepage Components\n **********\n");
        }

        if(isset($options['status'])) {
            $this->printStatus();
        }
        else {
            $this->refactorHomepages(!isset($options['skip-sql']), !isset($options['skip-clean-db']));
            $this->refactorHomepageTemplates(!isset($options['skip-sql']));

            if(!(isset($options['skip-instantiation']) || isset($options['skip-sql']))) {
                $this->instantiateComponents();
            }

            if(isset($options['create-components'])) {
                $this->generateMissingComponents();
            }
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
     * Method that does all the hard work on the homepages
     *
     * @param bool $performSQL
     * @param bool $cleanDB
     */
    public function refactorHomepages($performSQL, $cleanDB) {

        // if not doing SQL, forcibly prevent cleaning of the DB
        if(!$performSQL) {
            $cleanDB = false;
        }

        if($cleanDB) {
            // gather a list of all the things that need clearing
            $defIDs = $this->flatten($this->db->query("SELECT `id` FROM `component_definition` WHERE `module` = 'homepage'")->fetchAll(PDO::FETCH_ASSOC));
            $defList = implode(",", $defIDs);

            $conIDs = $this->flatten($this->db->query("SELECT `id` FROM `component_config_definition` WHERE `component_definition_id` IN ($defList)")->fetchAll(PDO::FETCH_ASSOC));
            $conList = implode(",", $conIDs);

            $this->cli("Deleting from component_config_instance", '-');
            $this->db->query("DELETE FROM `component_config_instance` WHERE `component_config_definition_id` IN ($conList)");

            $this->cli("Deleting from component_config_definition", '-');
            $this->db->query("DELETE FROM `component_config_definition` WHERE `component_definition_id` IN ($defList)");

            $this->cli("Deleting from component_instance", '-');
            $this->db->query("DELETE FROM `component_instance` WHERE `component_definition_id` IN ($defList)");

            $this->cli("Deleting from component_definition", '-');
            $this->db->query("DELETE FROM `component_definition` WHERE `id` IN ($defList)");

            // reset index counters on account of the mass deletion
            $this->cli("Resetting auto-increment counters");

            $this->db->query("ALTER TABLE `component_definition` AUTO_INCREMENT = 1");
            $this->db->query("ALTER TABLE `component_instance` AUTO_INCREMENT = 1");
            $this->db->query("ALTER TABLE `component_config_definition` AUTO_INCREMENT = 1");
            $this->db->query("ALTER TABLE `component_config_instance` AUTO_INCREMENT = 1");
        }

        $dataset = $this->db->query("SELECT * FROM `component_config_definition`")->fetchAll(PDO::FETCH_ASSOC);
        $existingConfigDefinitions = array();
        foreach($dataset as $d) {
            $existingConfigDefinitions[$d['key']] = $d;
        }
        unset($dataset);

        // get the highest index of definitions
        $i = $this->db->query("SELECT `id` FROM `component_definition` ORDER BY `id` DESC")->fetchColumn();

        $newDefinitions = array();

        $origin = $this->db->query("SELECT * FROM `homepage_comp`");

        while($oldComp = $origin->fetch(PDO::FETCH_ASSOC)) {
            $newComp = new Component_Definition();

            if(($newComp->class = $this->method2controller($oldComp['method'])) === false) {
                continue;
            }

            $this->cli("Converting {$oldComp['method']} to $newComp->class");

            if(isset($this->customNames[$newComp->class])) {
                $name = $this->customNames[$newComp->class];
            }
            else {
                $name = $oldComp['name'];
            }

            if(isset($this->customDescriptions[$newComp->class])) {
                $description = $this->customDescriptions[$newComp->class];
            }
            else {
                $description = $oldComp['description'];
            }

            $newComp->id = ++$i;
            $newComp->name = $name;
            $newComp->description = $description;
            $newComp->module = 'homepage';
            $newComp->componentCategory = $oldComp['homepageCategoryID'];
            $newComp->visible = ($oldComp['invisible'] == 'true' || in_array($newComp->class, $this->disableByDefault)) ? 'false' : 'true';
            $newComp->multiple = $oldComp['allowMultiple'];

            /* config definitions and instances */
            $this->parseSerializedConfig($oldComp['config'], $newComp);

            $newDefinitions[] = $newComp;
        }

        if($performSQL) {
            $this->cli("Inserting component definitions into database", '+');
            Component_DefinitionMapper::getInstance()->save($newDefinitions);
            $this->cli("Inserting component configuration definitions into database", '+');
            Component_ConfigDefinitionMapper::getInstance()->save($this->newConfigDefinitions);
        }

        /*
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
        */
    }


    /**
     * Method that does all the hard work on the homepage templates
     * @param $performSQL
     * @param $cleanDB
     */
    public function refactorHomepageTemplates($performSQL) {

        // select all the components in the existing template configuration
        $sql = "
            SELECT
                ht.id tid, ht.name, ht.left_column_width, ht.right_column_width,
                htl.componentId, htl.column, htl.position,
                hc.method
            FROM homepageTemplate ht
            LEFT JOIN homepageTemplateLayout htl ON ht.`id` = htl.`templateID`
            LEFT JOIN homepage_comp hc ON htl.componentId = hc.id
            ORDER BY ht.id ASC, htl.`column` ASC, htl.`position` ASC
        ";

        $this->cli("Refactoring homepage templates");

        $result = $this->db->query($sql);

        // build a neat little data structure
        $templates = array();
        $columns = array('left', 'right', 'top');

        while($row = $result->fetch(PDO::FETCH_ASSOC)) {

            if(!($class = $this->method2controller($row['method']))) {
                continue;
            }

            $stmt = $this->db->prepare("SELECT `id` FROM `component_definition` WHERE `class` = :cls");
            if(!$stmt->execute(array('cls' => $class))) {
                $this->cli("Couldn't find an appropriate definition for $class", '!');
                continue;
            }
            $compDefnID = $stmt->fetchColumn();

            $sql = "INSERT INTO `component_instance` (
                `component_definition_id`, `module`, `identifier`, `position`, `sequence`
            ) VALUES (
                :compDefnID, 'homepage_template', :templateID, :col, :seq
            )";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                'compDefnID' => $compDefnID,
                'templateID' => $row['tid'],
                'col'        => $columns[$row['column'] - 1],
                'seq'        => $row['position'],
            ));
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

                foreach($this->configKeyRemappings as $pattern => $replacement) {
                    if(preg_match($pattern, $key)) {
                        $key = $replacement;
                        break;
                    }
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

                $this->cli("  config: $key", ' ');

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

        $k = preg_replace("#Component_Homepage_([A-Za-z]*)Controller#i", "$1", $newComp->class);
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
        elseif(isset($this->customControllers[$method])) {
            $method = $this->customControllers[$method];
        }

        // fail
        else {
            $this->cli("Couldn't map method $method", '!');
            return false;
        }

        return sprintf($this->classBase, $method);
    }


    /**
     * @param $key
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
        if(isset($this->labelMappings[$key])) {
            return $this->labelMappings[$key];
        }
        elseif(strpos($key,'_') !== false) {
            return ucwords(str_replace('_', ' ', $key));
        }
        else {
            // by default, make camel-case human friendly
            return ucwords(preg_replace('#[A-Z][^A-Z]#sm',' $0', $key));
        }
    }


    /**
     * Generates PHP and HTML template files for those that have yet to be ported across.
     */
    public function generateMissingComponents() {
        $_DIR = realpath(dirname(__FILE__) . '/../../..');

        $phpTemplate  = 'library/Component/_ControllerTemplate.php';
        $htmlTemplate = 'templates/core/component/_templateTemplate.html';

        $result = $this->db->query("SELECT class FROM `component_definition` WHERE `module` = 'homepage'");

        while($class = $result->fetchColumn(0)) {
            if(!class_exists($class)) {
                // remap classname to filename
                $newPHPFile  = 'library/' . str_replace('_', '/', $class) . '.php';
                $component = preg_replace("#Component_Homepage_([A-Za-z]*)Controller#s", "$1", $class);
                $templateName = lcfirst($component);
                $newHTMLFile = "templates/core/component/homepage/$templateName.html";

                // create php file
                $this->cli("Creating $_DIR/$newPHPFile from $_DIR/$phpTemplate", '+');
                $phpContents = file_get_contents("$_DIR/$phpTemplate");
                $phpContents = str_replace('{MODULE}','Homepage',$phpContents);
                $phpContents = str_replace('{COMPONENT}', $component, $phpContents);
                $phpContents = str_replace('{COMPONENT_TEMPLATE}', $templateName, $phpContents);

                $fp = fopen("$_DIR/$newPHPFile", 'w');
                fwrite($fp,$phpContents);
                fclose($fp);

                // create html file
                $this->cli("Creating $_DIR/$newHTMLFile from $_DIR/$htmlTemplate", '+');
                copy("$_DIR/$htmlTemplate", "$_DIR/$newHTMLFile");
            }
        }
    }


    /**
     * After component definition has been run, this will iterate through every homepage and map the existing instances
     * to the new ones. Will possibly take a while to execute.
     */
    public function instantiateComponents() {

        $missingConfigs = array();

        $definitions = array();

        $defResult = $this->db->query("SELECT * FROM component_definition");

        while($defn = $defResult->fetch(PDO::FETCH_ASSOC)) {
            $definitions[$defn['class']] = $defn;
        }

        $configurations = array();
        $confResult = $this->db->query("SELECT * FROM component_config_definition");

        while ($c = $confResult->fetch(PDO::FETCH_ASSOC)) {
            $configurations[$c['component_definition_id']][$c['key']] = $c;
        }

        $result = $this->db->query("
        SELECT
            hcl.`cid` catID,
            hcl.`column` `column`,
            hcl.`position` sequence,
            hcl.`component` componentID,
            hcl.`config` config,
            hc.`method` method
        FROM `homepage_comp_location` hcl
        LEFT JOIN homepage_comp hc ON hcl.`component` = hc.`id`
        ORDER BY hcl.`cid` ASC, hcl.`column` ASC, hcl.`position` ASC
        ");

        // highest instance index
        $i = $this->db->query("SELECT MAX(`id`) FROM `component_instance`")->fetchColumn();
        $j = $this->db->query("SELECT MAX(`id`) FROM `component_config_instance`")->fetchColumn() + 1;

        $colNames = array('left','right','top');

        $this->cli("Inserting component instances into database", '+');

        while($instance = $result->fetch(PDO::FETCH_ASSOC)) {
            $i++;

            $oldConfig = $instance['config'] ? unserialize($instance['config']) : array();

            if(!($class = $this->method2controller($instance['method']))) {
                // le fail
                continue;
            }

            // build some queries to execute
            $sql = "INSERT INTO `component_instance` VALUES (
                :id, :cdi, 'homepage', :identifier, :posn, :seq
            )";

            // insert instance
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                'id'         => $i,
                'cdi'        => $definitions[$class]['id'],
                'identifier' => $instance['catID'],
                'posn'       => $colNames[$instance['column'] - 1],
                'seq'        => $instance['sequence'],
            ));

            // insert instance's config
            if(count($oldConfig)) {

                foreach($oldConfig as $key => $value) {

                    // this has been deprecated
                    if($key == 'filterOptions') {
                        continue;
                    }

                    foreach($this->configKeyRemappings as $pattern => $replacement) {
                        if(preg_match($pattern, $key)) {
                            $key = $replacement;
                            break;
                        }
                    }

                    // will only ever be binary
                    if(is_array($value)) {
                        $temp = 0;
                        $ord = 0;
                        foreach($value as $v) {
                            $temp += ($v) ? pow(2, $ord) : 0;
                            $ord++;
                        }
                        $value = $temp;
                    }

                    // there are some ridiculous data in the dev databse (probably worse in production) so do some
                    // sanity checking (though the output to check back over is useful)
                    if(!isset($configurations[$definitions[$class]['id']][$key])) {
                        $missingConfigs[$class][$key] = true;
                    }
                    else {
                        $stmt = $this->db->prepare("INSERT INTO `component_config_instance` VALUES (:id, :ccdi, :cii, :val)");
                        $stmt->execute(array(
                            'id'   => $j++,
                            'ccdi' => $configurations[$definitions[$class]['id']][$key]['id'],
                            'cii'  => $i,
                            'val'  => $value,
                        ));

                    } // if config key set

                } // foreach oldConfig

            } // count oldConfig

        } // while fetch instance

        $this->cli("DONE!");

        if(count($missingConfigs)) {
            foreach($missingConfigs as $class => $missing) {
                $this->cli(sprintf("Possibly missing configs for %s: %s", $class, implode(', ', array_keys($missing))), '?');
            }
        }

    } // method


    /**
     * Takes sequential array of array and flattens into a single array
     * @param $array
     * @return array
     */
    public function flatten($array) {
        if(count($array)) {
            $replacement = array();
            foreach($array as $i) {
                if(count($i)) {
                    foreach ($i as $k => $v) {
                        $replacement[] = $v;
                    }
                }
                else {
                    $replacement[] = $i;
                }
            }
            return $replacement;
        }
        return $array;
    }

    /**
     * @param $msg
     * @param string $error
     */
    public function cli($msg, $type = ' ') {
        if($this->beVerbose || in_array($type[0], array('?', '!', '*'))) {
            printf(" [%s] %s\n", $type[0], $msg);
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

