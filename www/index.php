<?php 
require_once('../include/sitePage.php');

if(isset($_GET['page'])) {
	$pageName = $_GET['page'];
}
else
	$pageName = 'home';

$page = new sitePage();

$page->loadContent($pageName);

$page->outputPage();


?>

