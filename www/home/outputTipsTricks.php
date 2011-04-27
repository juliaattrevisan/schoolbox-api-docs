<?php

define('TIPS_CID', 5);

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/categoryNews.php';

$newsHandler = CategoryNews;

$news = $newsHandler->getAllForCategory(TIPS_CID, array('size',1) );

$this->setVar('TIPS_TRICKS_HEADING', scrubTEXT($news[0]['heading']));
$this->setVar('TIPS_TRICKS', scrubHTML($news[0]['body']));

?>