<?php 

define( 'NEWS_PAGE', '1791');

	# Config for SynergyOne
define( 'DB_HOST_SYNERGY', 'mgsadmindb');
define( 'DB_USER_SYNERGY', 'intranet');
define( 'DB_PASSWD_SYNERGY', 'Qu4LitY');
define( 'DB_NAME_SYNERGY', 'SynergyOne');

	# MySQL config options
define( 'SQL_host', 'localhost');
define( 'SQL_user', 'helpdesk');
define( 'SQL_passwd', 'n00tbOOKS');
define( 'SQL_db', 'helpdesknew');

require_once($_SERVER['DOCUMENT_ROOT'] . "/../include/db.php");

	# Templates setup
define( 'TPL_DIR', 'templates');
require_once("template.php");

?>
