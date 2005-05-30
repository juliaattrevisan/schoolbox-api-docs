<?php

// File		: task.php
// Description	: Edit/add task for intranet team
// Written	: Nick Doyle, 11/6/2002
// Notes	: Hardcoded priorities, userids and statii mean these should be put in 
//		  a database if applying to a wider audience/usage
// Still requires access control

include( 'session.php');
include( 'include.php');

$taskId = $_POST[ 'task_id'];
$page_title = 'Portal Development - ';

$dbConnection = new dbManager;
$dbConnection->dbConnect();

	// submitted to self, do update/add
if( $_POST[ 'function'] == 'Update') {
	$arrTuples = array(	'assigned_user' => $_POST[ 'assigned_user'],
				'priority' => $_POST[ 'priority'],
				'status' => $_POST[ 'status'],
				'description' => $_POST[ 'description'], 
				'notes' => $_POST[ 'notes'], 
				'contact_name' => $_POST[ 'contact_name'], 
				'contact_phone' => $_POST[ 'contact_phone'], 
				'time_required' => $_POST[ 'time_required'], 
				'eta' => $_POST[ 'eta'] 
			);
	$query = 'update task set ';
	foreach( array_keys( $arrTuples) as $name) {
		$query .= "$name = " . sqlNull( $arrTuples[ $name]) . ",";
	}
	$query = rtrim( $query, ',');		// trim of trailing comma and whitespace
	$query .= " where task_id = '" . $_POST[ 'task_id'] . "'";
#print $query;
#exit;
	$dbConnection->dbDoQuery( $query);
	header( 'Location: task.php?javascript_alert=Updated');
}
elseif( $_POST[ 'function'] == 'Create') {
	$arrTuples =	array(	'created_user' => sqlNull( $_POST['created_user']),
				'created_date' => sqlNull( $_POST['created_date']),
				'assigned_user' => sqlNull( $_POST['assigned_user']),
				'priority' => sqlNull( $_POST['priority']),
				'status' => sqlNull( $_POST['status']),
				'description' => sqlNull( $_POST['description']),
				'notes' => sqlNull( $_POST['notes']),
				'contact_name' => sqlNull( $_POST['contact_name']) ,
				'contact_phone' => sqlNull( $_POST['contact_phone']),
				'time_required' => sqlNull( $_POST['time_required']),
				'eta' => sqlNull( $_POST['eta'])
			);
	$vars = implode( ',', array_keys( $arrTuples));
	$values = implode( ', ', array_values( $arrTuples));
	$query=	"insert into task ( $vars) values ( $values)";
	$dbConnection->dbDoQuery( $query);
	header( 'Location: task.php?javascript_alert=Created');
	exit;
	
}
if( $taskId =='' ) {
	$page_title .= 'New task';
	$function = 'Create';
}
else {
	$page_title .= 'Edit task';
	$function = 'Update';
}

$t = new Template( TPL_directory);

	// Setup main page's template
$t->set_file( 'tplBODY', 'modtask.htm');

$t->set_var( 'TITLE', $page_title);

$query=	'select	t.description,
		t.priority,
		t.notes,
		t.assigned_user as assigned_user_id,
		u.username as created_user,
		t.status,
		t.created_date
	from	(task t
		left join
		user u
		on t.created_user = u.id)
';
if( $taskId == '') {
	$query .= ' where 1=0';
}
else {
	$query .= " where task_id=$taskId";
}

$dbConnection->dbDoQuery( $query);
$result = $dbConnection->dbGetNextArray();

$t->set_var( 'JAVASCRIPT', $javascript);
$t->set_var( 'FUNCTION', $function);
$t->set_var( 'TASK_ID', $task_id);
$t->set_var( 'CREATED_USER', $result[ 'created_user']);
$t->set_var( 'CREATED_DATE', $result[ 'created_date']);
$t->set_var( 'CONTACT_NAME', $result[ 'contact_name']);
$t->set_var( 'CONTACT_PHONE', $result[ 'contact_phone']);
$t->set_var( 'TIME_REQUIRED', $result[ 'time_required']);
$t->set_var( 'ETA', $result[ 'eta']);
$t->set_var( 'DESCRIPTION', $result[ 'description']);
$t->set_var( 'NOTES', $result[ 'notes']);

	// Status
$html_status = html_select( 'status', array( '0%'=>0,'1% - 50%'=>1,'51% - 99%'=>2,'100%'=>3), $result[ 'status']);
$t->set_var( 'STATUS', $html_status);

	// User
$arrUser = array( 'Nick' => '778', 'James' => '4', 'Sue' => '9');
$html_user = html_select( 'assigned_user', $arrUser, $result[ 'assigned_user_id']);
$t->set_var( 'ASSIGNED_USER', $html_user);

	// Priority
$arrPriority = array();
for( $i=10; $i >-1; $i--) {
	$arrPriority[ $i] = $i;
}
$html_priority = html_select( 'priority', $arrPriority, $result[ 'priority']);
$t->set_var( 'PRIORITY', $html_priority);

$dbConnection->dbClose();

	// Parse items block into body
$t->pparse( 'parsedWhole', 'tplBODY');

?>
