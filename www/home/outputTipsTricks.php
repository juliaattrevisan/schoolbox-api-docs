<?php

define('TIPS_CID', 1699);
print "blah";

require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/categoryNews.php';

$newsHandler = new CategoryNews;

$news = $newsHandler->getAllForCategory(TIPS_CID, array('size',1) );
if(is_array($news)) {
    $this->setVar('TIPS_TRICKS_HEADING', scrubTEXT($news[0]['heading']));
    $this->setVar('TIPS_TRICKS', scrubHTML($news[0]['body']));

    $this->parse('POS'.$component['position'], $component['name']);
}
else {
    $this->setVar('POS'.$component['position'], '');
}
    ?>