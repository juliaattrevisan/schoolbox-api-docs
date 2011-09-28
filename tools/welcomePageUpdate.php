<?php

$componentMap = array(1=>4,2=>5,3=>3,4=>6,5=>7,6=>1,7=>8,8=>11,9=>17,10=>13,11=>16,12=>14);
$componentPosMap = array(0=>'top', 1=>'left',2=>'left',3=>'left',4=>'left', 5=>'right',6=>'right',7=>'right',8=>'right',9=>'right');

/* Foreach group extract existing welcome layout */

$groupList = $userHandle->getGroupList();

if ( is_array($groupList) ) {
    foreach ( $groupList as $group ) {

        /* Insert greeting into everyone */
        $instance = new Component_Instance;
        $definition = Component_DefinitionMapper::getInstance()->findById(2);
        $instance->definitionId = $definition->id;
        $instance->module       = 'dashboard';
        $instance->identifier   = $group['id'];
        $instance->position     = 'top';
        $instance->sequence     = 1;
        Component_InstanceMapper::getInstance()->save($instance);

        $query = "SELECT position, name, filename, function, componentID
            FROM welcomeLayout LEFT JOIN welcomeComponent ON componentID = welcomeComponent.id
            WHERE gid = {$_SESSION['gid']}";
        $result = $this->db->query($query);

        while($component = $this->db->getNextArray($result)){

            if($componentMap[$component['componentID']]) {
                if ( $definition = Component_DefinitionMapper::getInstance()->findById($componentMap[$component['componentID']]) ) {
                    $instance = new Component_Instance;
                    $instance->definitionId = $definition->id;
                    $instance->module       = 'dashboard';
                    $instance->identifier   = $group['id'];
                    $instance->position     = $componentPosMap[$component['position']];
                    $instance->sequence     = $component['position']?$component['position']:2;

                    Component_InstanceMapper::getInstance()->save($instance);
                }
            }
        }
        

    }
}
/* Insert 

?>