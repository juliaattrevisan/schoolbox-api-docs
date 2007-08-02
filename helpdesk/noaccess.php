<?PHP

require_once( 'include/helpDeskPage.php');

define( '_TITLE', 'Permission Denied');

$page = new HelpDeskPage;
$page->setTitle( _TITLE);

$page->setContent("<h2 style='margin:0px'>Error</h2><br><p> Sorry, You do not have permission to view this page <br> Click <a href='#' onClick = 'history.back()'>Here</a> to go Back</p>");
$page->printPage();