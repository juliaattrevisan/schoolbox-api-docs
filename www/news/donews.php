<?php

require_once("../include/include.php");
require_once("../include/session.php");
require_once("../include/staffonly.php");

require_once("news.php");

$myNews = new news();
if (isset($_POST['delete']) && isset($_POST['id']) && $_POST['delete'] == "Delete News" && $_POST['id'] > 0) {
	$myNews->deleteNews($_POST['id'], $_POST['myInfo']);
} else {
	$error = "";
	if (!is_array($_POST['campus']) && isset($_POST['campus']))
		$error .= isset($_POST['campus']). "/" .!is_numeric($_POST['campus']). "/ ".!is_array($_POST['campus'])."/".$_POST['campus']."Invalid Campus Selected<br>";
	if (!is_array($_POST['group']) || count($_POST['group']) < 1)
		$error .= "Invalid Group Selected<br>";
	if (!isset($_POST['heading']) || strlen($_POST['heading']) < 1 || strlen($_POST['heading']) > 50)
		$error .= "Your news heading is either too long or too short<br>";
	if (!isset($_POST['body']) || strlen($_POST['body']) < 1 || strlen($_POST['body']) > 65535)
		$error = ".Your news body is either too long or too short<br>";
	if (!isset($_POST['expires']) || $_POST['expires'] < 0)
		$expires = 24; else $expires = $_POST['expires'];
	if (!isset($_POST['mainpage']) || $_POST['mainpage'] != 1)
		$mainpage = 0; else $mainpage = $_POST['mainpage'];
	if (!isset($_POST['url1'])) $url1 = 0; else $url1 = $_POST['url1'];
	if (!isset($_POST['url2'])) $url2 = 0; else $url2 = $_POST['url2'];
	if (!isset($_POST['url3'])) $url3 = 0; else $url3 = $_POST['url3'];
	if (!isset($_POST['url4'])) $url4 = 0; else $url4 = $_POST['url4'];
	if (!isset($_POST['url5'])) $url5 = 0; else $url5 = $_POST['url5'];
	if (strlen($url1) > 255)
		$error .= "Your supplied URL 1 is too long<br>";
	if (strlen($url2) > 255)
		$error .= "Your supplied URL 2 is too long<br>";
	if (strlen($url3) > 255)
		$error .= "Your supplied URL 3 is too long<br>";
	if (strlen($url4) > 255)
		$error .= "Your supplied URL 4 is too long<br>";
	if (strlen($url5) > 255)
		$error .= "Your supplied URL 5 is too long<br>";
	if (!$error) {

		if (!isset($_POST['id']) || $_POST['id'] == 0) {
			$myNews->addNews($_POST['group'], $_POST['campus'], $_SESSION['id'], $_POST['heading'], $expires, $mainpage, 
			$_POST['body'], $_FILES['image']['tmp_name'], $_FILES['image']['type'], $_FILES['image']['name'], 
			$url1, $url2, $url3, $url4, $url5, $_POST['myInfo']);
		} else {
			$myNews->modNews($_POST['id'], $_POST['campus'], $_POST['group'], $_POST['heading'], $expires, $mainpage, $_POST['body'], $url1, 
			$url2, $url3, $url4, $url5, $_POST['$myInfo']);
		}
	} else {
		error(9, $error);
	}
}

localRedir("/news.php");

?>
