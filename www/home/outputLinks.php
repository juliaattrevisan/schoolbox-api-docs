<?php
$this->setBlock($component['name'], 'quicklink', 'quicklink_block');

require_once('../library/link.php');

if($_SESSION['campus'][0] == 1) //Senior
    $cid = 6022;

if($_SESSION['campus'][0] == 2) //Junior
    $cid = 6023;

if($_SESSION['campus'][0] == 3) //Middle
    $cid = 6024;

$linkHandler = new Link;

$linkList = $linkHandler->getAllInCategory($cid);

if(is_array($linkList)) {
    foreach($linkList as $link) {
        $this->setVar(array(
            'ON_OFF'    => ++$j%2 ? '' : 'dark',
            'LINK_TITLE' => scrubTEXT($link['description']),
            'LINK_URL'   => scrubHTML($link['url'])
        ));

        $this->parse("quicklink_block", "quicklink", true);
    }

    $this->parse('POS'.$component['position'], $component['name']);
} else {
    $this->setVar('POS'.$component['position'], '');
}

?>