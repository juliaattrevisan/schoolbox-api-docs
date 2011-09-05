<?php
$this->setBlock($component['name'], 'quicklink', 'quicklink_block');

require_once('../library/link.php');

foreach($_SESSION['campus'] as $campus) {

    if($campus == 1) //Senior
        $cidList[] = 6022;

    if($campus == 2) //Junior
        $cidList[] = 6023;

    if($campus == 3) //Middle
        $cidList[] = 6024;
}

$linkHandler = new Link;
$linkList = array();

foreach($cidList as $cid) {
    $tmp = $linkHandler->getAllInCategory($cid);
    $linkList = array_merge($linkList, $tmp);
}
if(count($linkList)>0) {
    foreach($linkList as $link) {
        $this->setVar(array(
            'ON_OFF'    => ++$j%2 ? '' : 'dark',
            'LINK_TITLE' => Scrub::text($link['description']),
            'LINK_URL'   => Scrub::html($link['url'])
        ));

        $this->parse("quicklink_block", "quicklink", true);
    }

    $this->parse('POS'.$component['position'], $component['name']);
} else {
    $this->setVar('POS'.$component['position'], '');
}

?>