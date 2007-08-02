<?php
# File		: jobList.php
# Written	: Nick Doyle, 5/7/2002
# Description	: helpdesk job listing
#		  If no search criteria specified, lists the current user's jobs

$_TITLE = 'Provider List';

require_once( 'include/helpDeskPage.php');
require_once( 'include/common.php');
require_once( 'modules/moduleDealerList.php');

$page = new HelpDeskPage;
$page->authCheck(1);

		# If search parms are specified, search on them
if( sizeof( $_REQUEST) > 1) {
	if( isSet( $assignedUser)) {
		$_TITLE .= ' for ' .  $dealerID;
	}
	elseif( $forUser <> '') {
		$_TITLE .= ' created for ' . $forUser;
	}
		# Make array of parameters on which to search. Fields with value 'all' set to null.
	$searchParms =	array(	'dealerID' 	=> '',
				'description' 	=> '',
				'type' 	=> '',
				'name'=> '',
				'phoneno'=>'',
				'email'=>''
			);
	foreach( array_keys( $searchParms) as $key) {
		if( $_REQUEST[ $key] == 'all') {
			$searchParms[ $key] = '';
		}
		else {
			$searchParms[ $key] = $_REQUEST[ $key];
		}
	}
}
else
{
	//showall 
	$searchParms = array();
}

$page->setTitle( $_TITLE);

$moduleDealerList = new moduleDealerList( $searchParms);
$content = $moduleDealerList->getContent();

$page->setContent( $content);

$page->printPage();

?>
