<?php
# File		: search.php
# Written	: Nick Doyle, 3/12/2002
# Description	: helpdesk global search

$_TITLE = 'Global search';

session_start();

require_once( 'include/helpDeskPage.php');
require_once( 'include/common.php');
require_once( 'modules/moduleJobList.php');
require_once( 'modules/moduleEquipmentList.php');
require_once( 'modules/moduleUserList.php');
require_once( 'modules/moduleDealerList.php');

$searchTerm = $_REQUEST[ 'search'];

if( $searchTerm == '') {
	die( 'Tried to global search on empty term. Such insanity is not allowed =)');
}

	# Search for jobs
if(preg_match('/([[:alpha:]\']+) ([[:alpha:]\']+)/', $searchTerm, $name))
{
        $searchParms = array('assignedUser.firstname' => $name[1],
                        'assignedUser.lastname' => $name[2]
                        );
        $conjuct = 'and';
}
else
{
	$searchParms =  array(  'jobID'         => $searchTerm,
			'description'   => $searchTerm,
			'assignedUser.firstname' => $searchTerm,
			'assignedUser.lastname' => $searchTerm,
			'assignedUser.username' => $searchTerm,
	);
	$conjuct = 'or';
}
$moduleJobList = new ModuleJobList( $searchParms, $conjuct);
$content = $moduleJobList->getContent();


	# Equipment
$searchParms =	array(	'serial'			=> $searchTerm,
						'equipmentTypeID'	=> $searchTerm,
						'm.description'			=> $searchTerm,
						'b.description'			=> $searchTerm,
						'userID'		=> $searchTerm,
						'userNonSynergy' 	=> $searchTerm,
						'l.description'		=> $searchTerm 
);
$moduleEquipmentList = new ModuleEquipmentList( $searchParms, 'or');
$content .= $moduleEquipmentList->getContent();


# Users
if(preg_match('/([[:alpha:]\']+) ([[:alpha:]\']+)/', $searchTerm, $name))
{
	$searchParms = array('firstname' => $name[1],
			'lastname' => $name[2]
			);
	$conjuct = 'and';
}
else
{

	$searchParms =	array(	'firstname'	=> $searchTerm,
				'lastname'	=> $searchTerm,
				'username'	=> $searchTerm,
				'synergy_id'	=> $searchTerm,
	);
	$conjuct = 'or';

}

$moduleUserList = new ModuleUserList( $searchParms, $conjuct);
$content .= $moduleUserList->getContent();

	# Dealers
$searchParms = array( 'description' => $searchTerm,
						'name' => $searchTerm,
						);
$moduleDealerList = new ModuleDealerList( $searchParms, 'or');
$content .= $moduleDealerList->getContent();		

$page = new HelpDeskPage;
$page->authCheck(1);
$page->setTitle( $_TITLE);

$page->setContent( $content);

$page->printPage();

?>
