<?php

require_once '../library/eportfolio.php';
require_once '../library/eportfolio-blog.php';

$blogHandler = new ePortfolioBlog();

$this->setFile(TEMPLATE_content, 'eportfolio_blog/modify.html');

if ($this->arg[0] == 'new' && is_numeric($this->arg[1])) {
    $eportfolioHandler = new ePortfolio($this->arg[1]);

    if (!$eportfolioHandler->canEdit($this->arg[1])) {
		error(23);
    }

	$this->setTitle('Create Blog Entry');

	$this->setBlock(TEMPLATE_content, 'delete', 'delete_block');

	$this->setVar(array(
		'ACTION'		=> 'Create',
		'ENTRY_HEADING' => '',
		'ENTRY_BODY'    => '',
		'UID'			=> $this->arg[1],
		'ID'			=> '',
		'delete_block'	=> ''
	));
}
else if (is_numeric($this->arg[0])) {
	$blog = $blogHandler->getByID($this->arg[0]);
    $eportfolioHandler = new ePortfolio($blog['owner']);

	$this->setTitle('Modify Blog Entry');

    if (!$eportfolioHandler->canEdit($blog['owner'])) {
        error(23);
    }

	$this->setVar(array(
		'ACTION'		=> 'Modify',
		'ENTRY_HEADING' => Scrub::text($blog['heading']),
		'ENTRY_BODY'    => Scrub::html($blog['body']),
		'UID'			=> $blog['owner'],
		'ID'			=> $this->arg[0],
	));
}

?>