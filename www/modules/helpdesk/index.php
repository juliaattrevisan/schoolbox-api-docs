<?php

require_once(HELPDESK_location.'/include/sql.php');

define( 'DB_NAME_PORTAL', SQL_db);

$userHandler = new User;
$groupHandler = new Group;

$db = new DeprecatedDatabase(SQL_db, 1);

//setup file
$this->setFile(TEMPLATE_content, 'modules/helpdesk/display.html');

$this->setTitle("Helpdesk");

//setup blocks
$this->setBlock(TEMPLATE_content, "user", "userlist");
$this->setBlock("user", "job", "joblist");
$this->setBlock("user", "equip", "equiplist");

if(is_numeric($this->arg[0]) && $this->arg[0] > 0 && ($groupHandler->isStaff() || $groupHandler->isParentOfChild($this->arg[0])) ) {
    $userList[] = $this->arg[0];
}
else if($groupHandler->isParent($_SESSION['gid']) && is_array($_SESSION['children'])) {
    foreach($_SESSION['children'] as $child) {
        $userList[] = $child['id'];
    }
}
else
    $userList[] = $_SESSION['id'];


foreach($userList as $userID) {
    $user = $userHandler->getByID($userID);
    $this->setVar("USER_CURR", $userHandler->formatName($user));

    /* Output user jobs */
    $parms = array( 'portalID'              => $userID,
                    'status'                => 'all',
                    'equipmentPortalUserID' => $userID,
                  );
    $query = SQL_jobList( $parms, 'or');

    $db->dbChangedb(HELPDESK_db);
    $jobs = $db->getAll($query);

    if(is_array($jobs)){
            foreach ($jobs as $job) {
                    $this->setVar("problem", ucfirst($job['problem']));
                    $this->setVar("description", ucfirst($job['description']));
                    $this->setVar("date", $job['createdDate']);
                    $this->setVar("jobID", $job['jobID']);
                    $this->setVar("assignedUserName", $job['assignedUserName']);
                    $this->setVar("status", $job['status']);
                    $this->parse("joblist", "job", true);
            }
    }
    else
            $this->setVar('joblist', '');

    /* Output equipment list */
    $params = array('portalUserID'	=> $userID,
                    'active'		=> 1
                                    );
    $db->dbChangedb(HELPDESK_db);
    $query = SQL_equipmentList($params, false);
    $equipment = $db->getAll($query);

    if(is_array($equipment)){
            foreach($equipment as $equip)
            {
                    $this->setVar("MODEL", $equip['model']);
                    $this->setVar("BRAND", $equip['brand']);
                    $this->setVar("TYPE", $equip['equipmentType']);
                    $this->setVar("LOCATION", $equip['location']);
                    $this->parse("equiplist", "equip", true);
            }
    }
    else
            $this->setVar('equiplist', '');

    $this->parse('userlist', 'user', true);


}

$db->dbChangedb(SQL_db);
?>
