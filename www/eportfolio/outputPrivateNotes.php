<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/eportfolio-private-notes.php';

$privateNotesHandler = new ePortfolioPrivateNotes;

$privateNotes = $privateNotesHandler->getByID($userID);

$this->setBlock(TEMPLATE_content, 'privateNotes', 'privateNotes_block');

$this->setVar(array(
    'PRIVATE_NOTES' => $privateNotes['privateNotes']
));

if ($eportfolioHandler->canEditPrivateNotes($userID)) {
    $this->parse('privateNotes_block', 'privateNotes');
}
else {
    $this->parse('privateNotes_block', '');
}
?>