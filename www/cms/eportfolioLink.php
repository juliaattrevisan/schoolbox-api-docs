<?php

require_once '../library/eportfolio.php';
require_once '../library/eportfolio-link.php';

$eportfolioHandler = new ePortfolio($_SESSION['id']);
$linkHandler = new Eportfolio_Link();

if (is_numeric($this->arg[0])) {

	/* Modify existing link
     */
	$link = $linkHandler->getByID($this->arg[0]);

    if (!is_array($link)) {
		error(41);
    }

	/* Check permissions to submit
     */
    if (!$eportfolioHandler->canEdit($link['owner'])) {
        error(6);
    }

	$this->setFile(TEMPLATE_content, 'cms/modify-eportfolio-link.html');

	$this->setTitle('Modify Link');
	$this->setTab('resources');

	$output = array(
        'USER_ID'           => $link['owner'],
        'ACTION'			=> 'Modify',
        'LINK_ID'			=> $link['id'],
        'URL'				=> $link['url'],
        'DESCRIPTION'		=> Scrub::text($link['description']),
        'COMMENT'			=> Scrub::text($link['comment']),
        'HIDDEN_SELECTED'	=> $link['hidden'] > 0 ? 'checked="checked"' : '',
    );

	$this->setVar($output);
}
else {
	error(2);
}
