<?php

/** @var SitePage $this */
$this->setFile(TEMPLATE_content, 'cms/myLinks.html');
$this->setBlock(TEMPLATE_content, 'myLink', 'myLink_block');
$this->setBlock(TEMPLATE_content, 'empty_state', 'empty_state_block');


$bookmarkMapper = \Schoolbox\Bookmark\Mapper::getInstance();

$allLinks = $bookmarkMapper->findByColumn('owner', $_SESSION['id']);


if ($allLinks->count()) {
    $this->setVar('empty_state_block', '');
    /** @var \Schoolbox\Bookmark\Model $currentLink */
    foreach ($allLinks as $currentLink) {
        $this->setVar(array(
            'MYLINK_URL' => $currentLink->getUrl(),
            'MYLINK_DESCRIPTION' => \Scrub::text($currentLink->getDescription()),
            'MYLINK_ID' => $currentLink->getId(),
        ));

        $this->parse('myLink_block', 'myLink', true);
    }
} else {
    $this->setVar('myLink_block', '');
    $this->parse('empty_state_block', 'empty_state');
}
