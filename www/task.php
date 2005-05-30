<?php

// File		: task.php
// Description	: List tasks for intranet team
// Written	: Nick Doyle, 6/6/2002
// Purpose	: As well as description, to trial modular template structures

#include( 'session.php');
include( 'include.php');

$PAGE_TITLE = 'Portal Development - Task List';
$DBNAME = 'task';
$t = new Template( TPL_directory);

	// Setup main page's template
$t->set_file( 'tplBODY', 'task.htm');
$t->set_block( 'tplBODY', 'TASKS', 'blkTask');		// Extract block marked 'TASKS' from tblBody, making handle 'blkTask'

$t->set_var( 'TITLE', $PAGE_TITLE);
$t->set_var( 'JAVASCRIPT_ALERT', 'alert( ' . $_GET[ 'javascript_alert'] . ');');

$dbConnection = new dbManager;
$dbConnection->dbConnect();

if( $_POST[ 'delete'] <> '') {
	$query = "delete from task where task_id=" . $_POST['task_id'];
	$dbConnection->dbDoQuery( $query);
}


$query=	'select	t.task_id,
		t.description,
		t.priority,
		t.notes,
		u.username as assigned_user,
		t.status
	from	task t
		left join
		user u
		on t.assigned_user = u.id
	order by priority DESC, description';
$dbConnection->dbDoQuery( $query);

	// Populate the task list
while( $result = $dbConnection->dbGetNextArray())
{ 
	$t->set_var( 'DELETE',	"<form action='task.php' method='POST'>
				<input type='hidden' name='task_id' value='" . $result[ 'task_id'] . "'>
				<input class='button_modify' type='submit' name='delete' onclick='return confirm(\"Delete this item?\");' value='delete'>
				</form>");
	$t->set_var( 'MODIFY',	"<form action='modtask.php' method='POST'>
				<input type='hidden' name='task_id' value='" . $result[ 'task_id'] . "'>
				<input class='button_modify' type='submit' name='modify' value='modify'>
				</form>");
	$t->set_var( 'DESCRIPTION', $result[ 'description']);
	$t->set_var( 'PRIORITY', $result[ 'priority']);
	$t->set_var( 'NOTES', $result[ 'notes']);
	$t->set_var( 'ASSIGNED_TO', $result[ 'assigned_user']);
	$t->set_var( 'STATUS', $result[ 'status']);
	$t->parse( 'blkTask', 'TASKS', true);
}

$dbConnection->dbClose();

	// Parse items block into body
$t->pparse( 'parsedWhole', 'tplBODY');

?>
