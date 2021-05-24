<?php

$this->setFile(TEMPLATE_content, 'cms/myLinks.html');
$this->setBlock(TEMPLATE_content, 'myLink', 'myLink_block');
$this->setBlock(TEMPLATE_content, 'empty_state', 'empty_state_block');


$myLinksHandler = new MyLinks();

$allLinks = $myLinksHandler->getAllByField('owner', $_SESSION['id']);

if (is_array($allLinks)) {
    $this->setVar('empty_state_block', '');
    foreach ($allLinks as $currentLink) {
        $this->setVar(array(
            'MYLINK_URL' => $currentLink['url'],
            'MYLINK_DESCRIPTION' => \Scrub::text($currentLink['description']),
            'MYLINK_ID' => $currentLink['id'],
        ));

        $this->parse('myLink_block', 'myLink', true);
    }
} else {
    $this->setVar('myLink_block', '');
    $this->parse('empty_state_block', 'empty_state');
}
