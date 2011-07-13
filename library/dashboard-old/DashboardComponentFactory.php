<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */
 
class DashboardComponentFactory {
    private static $instance;

    private function __construct () {

    }

    public function build ($component, $instance) {

        if ( !is_array($component) || !is_array($instance) ) {
            return false;
        }

        $component_file = dirname(__FILE__) . "/../../www/dashboard/component/{$component['class']}.php";

        if ( file_exists($component_file) ) {

            require_once($component_file);

            if ( class_exists($component['class'])) {
                /* Instantiate option models */
                $option_default_model = new DashboardComponentOptionDefault($component['id']);
                $option_model = new DashboardComponentOption($instance['id'], $option_default_model);

                /* Prepare properties */
                $property_arr = $this->merge_properties($component, $instance);

                $argument_arr = array($property_arr, $this->component_model, $this, $option_model, $this->template_model);

                /* Load dependencies */
                $dependency_arr = $this->load_dependencies($component);

                /* Instantiate component */
                $rc = new ReflectionClass($component['class']);
                $instance_obj = $rc->newInstanceArgs($argument_arr);

                /* Execute component init */
                $rm = new ReflectionMethod($component['class'], 'init');
                $rm->invokeArgs($instance_obj, $dependency_arr);

                return $instance_obj;
            }

        }

        return false;
    }

    private function load_dependencies ($component) {
        $dependency_arr = explode(',', trim($component['dependencies']));

        if ( is_array($dependency_arr) ) {
            $dependency_path = dirname(__FILE__) . '/../../library/';

            foreach ($dependency_arr as &$dependency) {
                $dependency = trim($dependency);

                if ( class_exists($dependency) ) {
                    $r = new ReflectionClass($dependency);

                    $dependency = $r->newInstance();
                } else {
                    $dependency = false;
                }
            }
        }

        return $dependency_arr;

    }

    private function merge_properties ($component, $instance) {
        $property_arr = array(
            'id'           => $instance['id'],
            'component_id' => $component['id'],
            'instance_id'  => $instance['id'],
            'group_id'     => $instance['group_id'],
            'class'        => $component['class'],
            'template'     => $component['template'],
            'position'     => $instance['position'],
            'order'        => $instance['order'],
            'name'         => $component['name'],
            'description'  => $component['description']
        );

        return $property_arr;
    }
}

/*
$instanceCollection = new InstanceCollection();
$instanceCollection->getAllByGroup($groupID)->toArray();

$instanceCollection2 = new InstanceCollection();
$instanceCollection2->getAllByGroup($groupID2)->toArray();

$instanceCollection3 = new InstanceCollection();
$instanceCollection3->getAllByGroup($groupID3)->toArray();

InstanceCollection::setHydrateMethod('Array');

$ic1 = InstanceCollection::getAllByGroup($groupId);
$ic2 = InstanceCollection::getAllByGroup($groupId2);
$ic3 = InstanceCollection::getAllWhereIdsIN(array(1,2,3,4,5,5))

$ic1->toArray()


class InstanceCollection {
    private $myCollection;

    public function __construct($myCollection) {
        $this->myCollection = $myCollection;
    }

    public function getAllByGroup() {
        $result = $this->performsomethingon($this->myCollection);

        return $result;
    }
}



class InstanceCollection extends Collection {

        public function getInstancesWhereType($type) {

        }
}

$synCollection = new SynergeticCollection(new MSSQL())
$tasCollection = new TasCollection(new MSSQl())


interface ExternalInterface {
    public getStudents();
    public getParents();
    public getAllSpouses();
    public getAlLDisabledKids();
}

class Synergetic extends Collection implements ExternalInterface {

    public getAllChildren() {
        $allKids = new SynergeticCollection()
    }
}

class Tass extends Collection implements ExternalInterface {
    private $db = New oracle();
}


abstract class Collection implements CollectionInterface {
    public $hydrationMethod = 'Array';
    public $limit = 10;
    public $order = 'desc';
    public $db = Db;

    public function __construct($specialDb) {
        if ($specialDb != null) {
            $this-> = $specialDb;
        }
    }
    public static function getAllByGroup($myCollection) {
        $result = self::performsomethingon($myCollection, self::$hydrationMethod);

        return $result;
    }

    public function getAllWhereIdsIn($arrayOfIds) {
        $arrayofobjects = Db::query($query);
    }

    public function updateNames($name) {
        foreach($arrayofobjects as $current) {
            $current->name = $name;
            $current->save();
        }
    }

    public function setHydrateMethod($type) {
        $this->hydrationMethod = $type;
    }
}


*/

/*


modules
    dueWork
        html
            detail.html
            timetable.html
        lib
            classes and collections and shit.php

    assess
        html
        lib

    submissions_working_v2
        html
            allSubmissionbyDate.html
        lib
            submissionModel.php
            submissionCollection.php

    submissions_working_v2
        html
            allSubmissionbyDate.html
        lib
            submissionModel.php
            submissionCollection.php

www
    home.php
 */

?>