<?php
# File		: userDetails.php
# Written	: Nick Doyle, 1/7/2002
# Description	: helpdesk user details page

require_once( 'include/helpDeskPage.php');
require_once( 'modules/moduleUserSearch.php');

define( '_TITLE', 'User Search');

printTemplate( _TITLE, 'userSearch.html');

?>
